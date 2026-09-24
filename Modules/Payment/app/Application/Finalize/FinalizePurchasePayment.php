<?php

namespace Modules\Payment\Application\Finalize;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\Journal\RecordJournal;
use Modules\Accounting\Application\Journal\ResolvePayableAccount;
use Modules\Payment\Models\PurchasePayment;
use Modules\Purchasing\Application\PurchaseInvoice\ApplyInvoicePayment;
use Modules\Purchasing\Application\SupplierMemo\ApplyDebitMemo;

/**
 * Finalisasi pembayaran: post jurnal + apply ke faktur + kurangi uang muka
 * & Debit Memo. Dipakai jalur auto-final dan listener approval.
 * Idempoten: payment non-pending = no-op.
 */
class FinalizePurchasePayment
{
    public function __construct(
        private readonly RecordJournal $recordJournal,
        private readonly ResolvePayableAccount $payableAccounts,
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly ApplyInvoicePayment $applyInvoicePayment,
        private readonly ApplyDebitMemo $applyDebitMemo,
    ) {}

    public function execute(int $paymentId): PurchasePayment
    {
        return DB::transaction(function () use ($paymentId): PurchasePayment {
            $payment = PurchasePayment::with([
                'allocations',
                'withholdings',
                'depositUses',
                'memoUses',
            ])->whereKey($paymentId)->lockForUpdate()->firstOrFail();

            if ($payment->status !== 'pending') {
                return $payment;
            }

            if ($payment->mode === 'deposit') {
                $payment->update(['status' => 'approved']);

                return $payment->fresh();
            }

            $payableAccountId = $this->payableAccounts->execute((int) $payment->supplier_id)->id;
            $cashAccountId = (int) $payment->cash_account_id;
            $lines = [];

            // Apply ke faktur: Dr Hutang (alokasi) / Cr Kas (cash_out).
            foreach ($payment->allocations as $allocation) {
                $this->applyInvoicePayment->execute(
                    (int) $allocation->purchase_invoice_id,
                    (float) $allocation->amount,
                    'purchase_payment',
                    (int) $payment->id
                );
            }

            $gross = (float) $payment->gross_amount;
            $withholding = (float) $payment->withholding_amount;
            $depositApplied = (float) $payment->deposit_applied;
            $memoApplied = (float) $payment->memo_applied;
            $cashOut = (float) $payment->cash_out;

            if ($gross > 0.005) {
                $lines[] = ['account_id' => $payableAccountId, 'debit' => round($gross, 2), 'credit' => 0];
            }

            if ($cashOut > 0.005) {
                $lines[] = ['account_id' => $cashAccountId, 'debit' => 0, 'credit' => round($cashOut, 2)];
            }

            // Withholding: Cr akun pemotongan.
            foreach ($payment->withholdings as $withholdingRow) {
                $amount = (float) $withholdingRow->amount;

                if ($amount > 0.005) {
                    $lines[] = [
                        'account_id' => (int) $withholdingRow->account_id,
                        'debit' => 0,
                        'credit' => round($amount, 2),
                    ];
                }
            }

            // Apply uang muka: Cr 1402 (Uang Muka Pembelian).
            if ($depositApplied > 0.005) {
                foreach ($payment->depositUses as $use) {
                    $source = PurchasePayment::whereKey((int) $use->source_payment_id)->lockForUpdate()->first();

                    if ($source) {
                        $source->update([
                            'deposit_remaining' => max(0.0, (float) $source->deposit_remaining - (float) $use->amount),
                        ]);
                    }
                }

                $depositAccount = $this->chartOfAccounts->findBySeedKey('accounting.coa.1402');

                if ($depositAccount) {
                    $lines[] = [
                        'account_id' => (int) $depositAccount['id'],
                        'debit' => 0,
                        'credit' => round($depositApplied, 2),
                    ];
                }
            }

            // Apply Debit Memo: Cr 1402 (kredit supplier).
            if ($memoApplied > 0.005) {
                foreach ($payment->memoUses as $use) {
                    $this->applyDebitMemo->execute(
                        (int) $use->supplier_debit_memo_id,
                        (float) $use->amount,
                        'purchase_payment',
                        (int) $payment->id
                    );
                }

                $memoAccount = $this->chartOfAccounts->findBySeedKey('accounting.coa.1402');

                if ($memoAccount) {
                    $lines[] = [
                        'account_id' => (int) $memoAccount['id'],
                        'debit' => 0,
                        'credit' => round($memoApplied, 2),
                    ];
                }
            }

            $this->recordJournal->execute([
                'branch_id' => (int) $payment->branch_id,
                'journal_date' => $payment->payment_date->toDateString(),
                'reference_type' => 'purchase_payment',
                'reference_id' => $payment->id,
                'memo' => 'Pembayaran pembelian '.$payment->number,
                'lines' => $lines,
            ]);

            $payment->update(['status' => 'approved']);

            return $payment->fresh();
        });
    }
}
