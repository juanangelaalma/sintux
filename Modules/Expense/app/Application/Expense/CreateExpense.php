<?php

namespace Modules\Expense\Application\Expense;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\TaxQuery;
use Modules\Contact\Application\GetContacts;
use Modules\Expense\Domain\Rules\CalculateExpenseTotals;
use Modules\Expense\Enums\ExpenseSource;
use Modules\Expense\Enums\ExpenseStatus;
use Modules\Expense\Models\Expense;
use Modules\Expense\Models\ExpenseAttachment;
use Modules\Expense\Models\ExpenseLine;
use Modules\Expense\Models\ExpenseTag;
use Modules\Payment\Application\GetPaymentMethods;

/**
 * Buat biaya: validasi master lintas modul, hitung total, simpan header dan
 * baris, pasang tag dan lampiran, lalu jurnalkan.
 *
 * Seluruhnya satu DB::transaction(). Jurnal dipanggil dari dalam transaksi
 * yang sama supaya biaya tanpa jurnal atau jurnal tanpa biaya mustahil.
 *
 * Master dari modul lain hanya lewat public API-nya: CoA dan pajak dari
 * Accounting, kontak dari Contact, cara pembayaran dari Payment. Tidak ada
 * import model, tabel, atau kueri langsung ke modul lain.
 */
class CreateExpense
{
    public function __construct(
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly TaxQuery $taxQuery,
        private readonly GetContacts $contacts,
        private readonly GetPaymentMethods $paymentMethods,
        private readonly CalculateExpenseTotals $totals,
        private readonly GenerateExpenseNumber $numbers,
        private readonly PostExpenseJournal $postJournal,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds  Cakupan cabang untuk resolusi master.
     * @param  int|null  $userId  Pembuat transaksi.
     * @param  list<array{file: UploadedFile}>  $attachments
     */
    public function execute(
        array $data,
        int $branchId,
        array $branchIds,
        ?int $userId = null,
        array $attachments = [],
    ): Expense {
        $context = $this->resolveContext($data, $branchId, $branchIds);

        return DB::transaction(function () use ($data, $branchId, $userId, $attachments, $context): Expense {
            $lines = $this->validateLines($data['lines'] ?? [], $context);

            if ($lines === []) {
                throw ValidationException::withMessages([
                    'lines' => 'Minimal satu baris akun biaya dengan jumlah lebih dari nol.',
                ]);
            }

            $withholding = $this->validateWithholding($data['withholding'] ?? null, $context, $lines);

            $computed = $this->totals->execute($lines, $withholding, (bool) ($data['is_tax_inclusive'] ?? false));

            // BR-08: bayar langsung lunas seketika. BR-09: bayar nanti
            // sampai dilunasi, jadi amount_paid nol dan sisa = grand_total.
            $isPayLater = (bool) ($data['is_pay_later'] ?? false);

            $expense = Expense::query()->create([
                'branch_id' => $branchId,
                ...$context['number'],
                'transaction_date' => (string) $data['transaction_date'],
                'pay_from_account_id' => $context['payFromAccountId'],
                'is_pay_later' => $isPayLater,
                'contact_id' => $context['contact']['id'] ?? null,
                'contact_name' => $context['contact']['name'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'payment_method_id' => $context['paymentMethodId'],
                // Q-16: tidak ada master mata uang dan tidak ada flag
                // multi-currency, jadi nilainya selalu IDR.
                'currency_code' => 'IDR',
                'is_tax_inclusive' => (bool) ($data['is_tax_inclusive'] ?? false),
                'withholding_type' => $withholding['type'] ?? null,
                'withholding_value' => $withholding['value'] ?? 0,
                'withholding_account_id' => $withholding['account_id'] ?? null,
                'withholding_total' => $computed['withholding_total'],
                'memo' => $data['memo'] ?? null,
                'subtotal' => $computed['subtotal'],
                'tax_total' => $computed['tax_total'],
                'grand_total' => $computed['grand_total'],
                'amount_paid' => $isPayLater ? 0 : $computed['grand_total'],
                'status' => $isPayLater ? ExpenseStatus::Open : ExpenseStatus::Closed,
                'source' => ExpenseSource::Manual,
                'created_by' => $userId,
            ]);

            foreach ($computed['lines'] as $index => $computedLine) {
                $input = $lines[$index];

                ExpenseLine::query()->create([
                    'expense_id' => (int) $expense->id,
                    'position' => $index + 1,
                    'account_id' => $input['account_id'],
                    'account_name' => $input['account_name'],
                    'description' => $input['description'] ?? null,
                    'tax_id' => $input['tax_id'],
                    'tax_rate' => $input['tax_rate'],
                    'amount' => $computedLine['amount'],
                    'amount_before_tax' => $computedLine['amount_before_tax'],
                    'tax_amount' => $computedLine['tax_amount'],
                    'tax_breakdown' => $computedLine['tax_breakdown'] === [] ? null : $computedLine['tax_breakdown'],
                ]);
            }

            $this->syncTags($expense, $data['tag_ids'] ?? []);
            $this->storeAttachments($expense, $attachments);

            $this->postJournal->execute($expense->load('lines'));

            return $expense->load(['lines', 'tags']);
        });
    }

    /**
     * Resolusi dan validasi master header. Semua lewat public API modul lain.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds
     * @return array{
     *     number: array{number: string, sequence: int},
     *     payFromAccountId: int|null,
     *     contact: array{id: int, name: string}|null,
     *     paymentMethodId: int,
     *     expenseAccounts: array<int, string>,
     *     taxes: array<int, array<string, mixed>>
     * }
     */
    private function resolveContext(array $data, int $branchId, array $branchIds): array
    {
        $isPayLater = (bool) ($data['is_pay_later'] ?? false);
        $payFromAccountId = null;

        // Q-03: kolom "Bayar dari" tidak divalidasi saat bayar nanti, dan
        // tidak dipakai untuk jurnal. Tetap disimpan supaya input user tidak
        // hilang bila checkbox dilepas lagi.
        if (! $isPayLater || ($data['pay_from_account_id'] ?? null) !== null) {
            $payFromAccountId = $this->resolvePayFromAccount($data, $isPayLater);
        }

        return [
            'number' => $this->resolveNumber($data),
            'payFromAccountId' => $payFromAccountId,
            'contact' => $this->resolveContact($data, $branchIds),
            'paymentMethodId' => $this->resolvePaymentMethod($data),
            'expenseAccounts' => $this->expenseAccountIndex(),
            'taxes' => $this->taxIndex(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{number: string, sequence: int}
     */
    private function resolveNumber(array $data): array
    {
        $number = $data['number'] ?? null;

        if ($number === null || $number === '') {
            return $this->numbers->execute();
        }

        $number = (string) $number;
        $this->numbers->assertNumberIsFree($number);

        return ['number' => $number, 'sequence' => 0];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePayFromAccount(array $data, bool $isPayLater): ?int
    {
        $accountId = $data['pay_from_account_id'] ?? null;

        if ($accountId === null || $accountId === '') {
            // Q-03: bayar nanti boleh tanpa akun kas/bank.
            if ($isPayLater) {
                return null;
            }

            throw ValidationException::withMessages([
                'pay_from_account_id' => 'Akun kas atau bank wajib dipilih untuk biaya yang dibayar langsung.',
            ]);
        }

        $cashAccountIds = array_column($this->chartOfAccounts->listCashAndBank(), 'id');

        if (! in_array((int) $accountId, array_map('intval', $cashAccountIds), true)) {
            throw ValidationException::withMessages([
                'pay_from_account_id' => 'Akun kas atau bank tidak valid.',
            ]);
        }

        return (int) $accountId;
    }

    /**
     * Penerima boleh kosong. Hanya tipe supplier dan karyawan yang ditawarkan
     * (Q-14); repo tidak punya tipe kontak "lainnya".
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds
     * @return array{id: int, name: string}|null
     */
    private function resolveContact(array $data, array $branchIds): ?array
    {
        $contactId = $data['contact_id'] ?? null;

        if ($contactId === null || $contactId === '') {
            return null;
        }

        // GetContacts menerima satu tipe per panggilan, jadi dua tipe berarti
        // dua panggilan lalu digabung. Tidak ada perubahan modul Contact.
        $candidates = collect($this->contacts->execute('supplier', $branchIds))
            ->merge($this->contacts->execute('employee', $branchIds))
            ->keyBy('id');

        $contact = $candidates->get((int) $contactId);

        if (! $contact) {
            throw ValidationException::withMessages([
                'contact_id' => 'Penerima tidak ditemukan dalam cakupan cabang Anda.',
            ]);
        }

        return ['id' => (int) $contact['id'], 'name' => (string) $contact['name']];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolvePaymentMethod(array $data): int
    {
        $methodId = $data['payment_method_id'] ?? null;
        $available = array_map(
            fn (array $method): int => (int) $method['id'],
            $this->paymentMethods->execute(),
        );

        if ($methodId === null || $methodId === '' || ! in_array((int) $methodId, $available, true)) {
            throw ValidationException::withMessages([
                'payment_method_id' => 'Cara pembayaran wajib dipilih.',
            ]);
        }

        return (int) $methodId;
    }

    /**
     * @return array<int, string> account id => account name
     */
    private function expenseAccountIndex(): array
    {
        $index = [];

        foreach ($this->chartOfAccounts->listForExpense() as $account) {
            $index[(int) $account['id']] = (string) $account['name'];
        }

        return $index;
    }

    /**
     * BR-02 dan Q-02: pajak baris memakai listForPurchase() karena biaya
     * recognized ke PPN Masukan. listForPurchase() juga menyembunyikan pajak
     * pemotongan, yang punya mekanisme sendiri di kolom 15.
     *
     * @return array<int, array<string, mixed>>
     */
    private function taxIndex(): array
    {
        $index = [];

        foreach ($this->taxQuery->listForPurchase() as $tax) {
            $index[(int) $tax['id']] = $tax;
        }

        return $index;
    }

    /**
     * BR-02 dan BR-03: baris harus punya akun biaya yang lolos BR-02 dan
     * jumlah lebih dari nol.
     *
     * @param  array<int, mixed>  $lines
     * @param  array<string, mixed>  $context
     * @return list<array{account_id: int, account_name: string, description: string|null, tax_id: int|null, tax_rate: float, amount: float, tax: array<string, mixed>|null}>
     */
    private function validateLines(array $lines, array $context): array
    {
        $validated = [];

        foreach ($lines as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $amount = (float) ($line['amount'] ?? 0);

            if ($accountId === 0 || ! isset($context['expenseAccounts'][$accountId])) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => 'Akun biaya tidak valid untuk biaya.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Jumlah baris harus lebih besar dari nol.',
                ]);
            }

            $taxId = $line['tax_id'] ?? null;
            $taxDef = null;
            $taxRate = 0.0;

            if ($taxId !== null && $taxId !== '' && $taxId !== 0) {
                $taxDef = $context['taxes'][(int) $taxId] ?? null;

                if (! $taxDef) {
                    throw ValidationException::withMessages([
                        "lines.{$index}.tax_id" => 'Pajak tidak valid untuk biaya.',
                    ]);
                }

                $taxRate = (float) $taxDef['rate'];
            }

            $validated[] = [
                'account_id' => $accountId,
                'account_name' => $context['expenseAccounts'][$accountId],
                'description' => $line['description'] ?? null,
                'tax_id' => $taxDef ? (int) $taxId : null,
                'tax_rate' => $taxRate,
                'amount' => $amount,
                'tax' => $taxDef,
            ];
        }

        return $validated;
    }

    /**
     * BR-05: persen 0-100, tipe nominal dipakai apa adanya, akun penampung
     * wajib dipilih bila pemotongan diisi.
     *
     * @param  array<string, mixed>|null  $withholding
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $lines
     * @return array{type: string, value: float, account_id: int}|null
     */
    private function validateWithholding(?array $withholding, array $context, array $lines): ?array
    {
        if (! $withholding) {
            return null;
        }

        $type = $withholding['type'] ?? null;
        $value = (float) ($withholding['value'] ?? 0);
        $accountId = $withholding['account_id'] ?? null;

        if ($type === null || $type === '') {
            return null;
        }

        if ($value <= 0) {
            throw ValidationException::withMessages([
                'withholding.value' => 'Jumlah pemotongan harus lebih besar dari nol.',
            ]);
        }

        if ($type === 'percent' && ($value < 0 || $value > 100)) {
            throw ValidationException::withMessages([
                'withholding.value' => 'Pemotongan persen harus antara 0 sampai 100.',
            ]);
        }

        if ($accountId === null || $accountId === '') {
            throw ValidationException::withMessages([
                'withholding.account_id' => 'Akun penampung pemotongan wajib dipilih.',
            ]);
        }

        if (! $this->chartOfAccounts->isEligible((int) $accountId)) {
            throw ValidationException::withMessages([
                'withholding.account_id' => 'Akun penampung pemotongan tidak valid.',
            ]);
        }

        return ['type' => $type, 'value' => $value, 'account_id' => (int) $accountId];
    }

    /**
     * @param  list<int>  $tagIds
     */
    private function syncTags(Expense $expense, array $tagIds): void
    {
        if ($tagIds === []) {
            return;
        }

        $available = array_map(
            fn (array $tag): int => (int) $tag['id'],
            ExpenseTag::query()->get(['id'])->map(fn (ExpenseTag $tag): array => ['id' => $tag->id])->all(),
        );

        $unknown = array_diff(array_map('intval', $tagIds), $available);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'tag_ids' => 'Tag tidak ditemukan.',
            ]);
        }

        $expense->tags()->sync(array_values(array_unique(array_map('intval', $tagIds))));
    }

    /**
     * @param  list<array{file: UploadedFile}>  $attachments
     */
    private function storeAttachments(Expense $expense, array $attachments): void
    {
        foreach ($attachments as $attachment) {
            $file = $attachment['file'];

            $attachmentModel = new ExpenseAttachment;
            $attachmentModel->expense_id = (int) $expense->id;
            $attachmentModel->path = $file->store('expenses/'.$expense->id, 'local');
            $attachmentModel->original_name = $file->getClientOriginalName();
            $attachmentModel->mime = $file->getClientMimeType();
            $attachmentModel->size = $file->getSize();
            $attachmentModel->save();
        }
    }
}
