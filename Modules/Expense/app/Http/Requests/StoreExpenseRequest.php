<?php

namespace Modules\Expense\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\TaxQuery;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Payment\Application\GetPaymentMethods;

/**
 * Validasi bentuk form biaya. Validasi bisnis (akun beban yang sah, pajak
 * yang eligible, pemotongan) tetap di CreateExpense supaya tidak terlewat
 * saat dipanggil dari jalur selain HTTP.
 */
class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $branchIds = CompanyAccess::contextBranchIds($this->user(), (string) session('active_tenant_id'))
            ?? CompanyAccess::accessibleBranchIds($this->user(), (string) session('active_tenant_id'));

        $expenseAccountIds = array_map(
            fn (array $account): int => (int) $account['id'],
            app(ChartOfAccountQuery::class)->listForExpense(),
        );

        $cashAccountIds = array_map(
            fn (array $account): int => (int) $account['id'],
            app(ChartOfAccountQuery::class)->listCashAndBank(),
        );

        // Q-14: Penerima hanya menawarkan supplier dan karyawan.
        $contactIds = collect($this->contacts('supplier', $branchIds))
            ->merge($this->contacts('employee', $branchIds))
            ->pluck('id')
            ->all();

        $taxIds = collect(app(TaxQuery::class)->listForPurchase())->pluck('id')->all();

        $paymentMethodIds = array_map(
            fn (array $method): int => (int) $method['id'],
            app(GetPaymentMethods::class)->execute(),
        );

        return [
            'pay_from_account_id' => ['nullable', 'integer', Rule::in($cashAccountIds)],
            'is_pay_later' => ['sometimes', 'boolean'],
            'contact_id' => ['nullable', 'integer', Rule::in($contactIds)],
            'transaction_date' => ['required', 'date'],
            'payment_method_id' => ['required', 'integer', Rule::in($paymentMethodIds)],
            'number' => ['nullable', 'string', 'max:60'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
            'billing_address' => ['nullable', 'string', 'max:2000'],
            'is_tax_inclusive' => ['sometimes', 'boolean'],
            'memo' => ['nullable', 'string', 'max:2000'],

            'withholding' => ['nullable', 'array'],
            'withholding.type' => ['nullable', 'string', Rule::in(['percent', 'nominal'])],
            'withholding.value' => ['nullable', 'numeric', 'gte:0', 'required_with:withholding.type'],
            'withholding.account_id' => ['nullable', 'integer'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.account_id' => ['required', 'integer', Rule::in($expenseAccountIds)],
            'lines.*.description' => ['nullable', 'string', 'max:2000'],
            'lines.*.tax_id' => ['nullable', 'integer', Rule::in($taxIds)],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],

            // PRD §7.2 kolom 17: maksimum 10 MB per file.
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pay_from_account_id.required' => 'Akun kas atau bank wajib dipilih untuk biaya yang dibayar langsung.',
            'lines.*.account_id.in' => 'Akun biaya tidak valid untuk biaya.',
            'lines.*.amount.gt' => 'Jumlah baris harus lebih besar dari nol.',
            'withholding.account_id.required' => 'Akun penampung pemotongan wajib dipilih.',
            'transaction_date.required' => 'Tanggal transaksi wajib diisi.',
            'payment_method_id.required' => 'Cara pembayaran wajib dipilih.',
            'attachments.*.max' => 'Ukuran maksimal 10 MB per file.',
        ];
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    private function contacts(string $type, array $branchIds): array
    {
        return app(GetContacts::class)->execute($type, $branchIds);
    }
}
