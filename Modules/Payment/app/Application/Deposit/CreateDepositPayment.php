<?php

namespace Modules\Payment\Application\Deposit;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\Journal\RecordJournal;
use Modules\Company\Application\CompanyAccess;
use Modules\Payment\Models\PurchasePayment;

/**
 * Catat uang muka (deposit) ke supplier: Dr 1402 Uang Muka Pembelian /
 * Cr akun kas. Tanpa alokasi faktur; apply saat pembayaran faktur
 * (Task 6/7).
 */
class CreateDepositPayment
{
    public function __construct(
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly RecordJournal $recordJournal,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, ?int $userId = null): PurchasePayment
    {
        return DB::transaction(function () use ($data, $userId): PurchasePayment {
            $hqBranchId = CompanyAccess::headquartersBranchId();
            $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : (int) ($hqBranchId ?? 0);

            if ($hqBranchId !== null && $branchId !== $hqBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Pembayaran hanya dapat dibuat untuk Head Office.',
                ]);
            }

            $amount = (float) ($data['amount'] ?? 0);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal uang muka harus lebih dari 0.',
                ]);
            }

            $cashAccountId = (int) ($data['cash_account_id'] ?? 0);

            if (! $this->chartOfAccounts->isEligible($cashAccountId)) {
                throw ValidationException::withMessages([
                    'cash_account_id' => 'Akun kas/bank tidak valid.',
                ]);
            }

            $depositAccount = $this->chartOfAccounts->findBySeedKey('accounting.coa.1402');

            if (! $depositAccount) {
                throw new \LogicException('Akun [accounting.coa.1402] tidak ditemukan.');
            }

            $payment = PurchasePayment::create([
                'number' => $this->nextNumber(),
                'branch_id' => $branchId,
                'supplier_id' => (int) $data['supplier_id'],
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'mode' => 'deposit',
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'due_date' => null,
                'currency_code' => (string) ($data['currency_code'] ?? 'IDR'),
                'cash_account_id' => $cashAccountId,
                'gross_amount' => 0,
                'withholding_amount' => 0,
                'deposit_applied' => 0,
                'memo_applied' => 0,
                'cash_out' => $amount,
                'deposit_total' => $amount,
                'deposit_remaining' => $amount,
                'status' => 'pending',
                'memo' => $data['memo'] ?? null,
                'created_by' => $userId,
            ]);

            // Jurnal: Dr 1402 Uang Muka Pembelian / Cr Kas.
            $this->recordJournal->execute([
                'branch_id' => $branchId,
                'journal_date' => $payment->payment_date->toDateString(),
                'reference_type' => 'purchase_payment',
                'reference_id' => $payment->id,
                'memo' => 'Uang muka pembelian '.$payment->number,
                'lines' => [
                    ['account_id' => (int) $depositAccount['id'], 'debit' => round($amount, 2), 'credit' => 0],
                    ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => round($amount, 2)],
                ],
            ]);

            $payment->update(['status' => 'approved']);

            return $payment->fresh();
        });
    }

    private function nextNumber(): string
    {
        DB::statement("SELECT pg_advisory_xact_lock(hashtext('purchase_payment_number'))");

        $date = now()->format('Ymd');
        $count = PurchasePayment::whereDate('created_at', today())->count() + 1;

        return sprintf('PBL/%s/%03d', $date, $count);
    }
}
