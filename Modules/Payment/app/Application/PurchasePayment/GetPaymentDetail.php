<?php

namespace Modules\Payment\Application\PurchasePayment;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\Journal\GetJournalByReference;
use Modules\Payment\Models\PurchasePayment;
use Modules\Purchasing\Application\PurchaseTag\GetPurchaseTags;

/**
 * Proyeksi detail pembayaran untuk halaman show: header + alokasi +
 * withholding + kredit (deposit/memo) + jurnal.
 *
 * @return array<string, mixed>
 */
class GetPaymentDetail
{
    public function __construct(
        private readonly GetJournalByReference $journals,
        private readonly GetPurchaseTags $purchaseTags,
    ) {}

    /**
     * Proyeksi detail pembayaran untuk halaman show: header + alokasi +
     * withholding + kredit (deposit/memo) + jurnal.
     *
     * @param  list<int>  $branchIds  Scope cabang peminta (wajib, otorisasi).
     * @return array<string, mixed>
     */
    public function execute(int $paymentId, array $branchIds = []): array
    {
        $payment = PurchasePayment::with([
            'allocations',
            'withholdings',
            'depositUses.sourcePayment',
            'memoUses',
            'paymentMethod',
        ])
            ->whereIn('branch_id', $branchIds)
            ->findOrFail($paymentId);

        return [
            'payment' => [
                'id' => (int) $payment->id,
                'number' => (string) $payment->number,
                'status' => (string) $payment->status,
                'mode' => (string) $payment->mode,
                'payment_date' => $payment->payment_date->toDateString(),
                'due_date' => $payment->due_date?->toDateString(),
                'currency_code' => (string) $payment->currency_code,
                'supplier_id' => (int) $payment->supplier_id,
                'payment_method' => $payment->paymentMethod?->name,
                'gross_amount' => (float) $payment->gross_amount,
                'withholding_amount' => (float) $payment->withholding_amount,
                'deposit_applied' => (float) $payment->deposit_applied,
                'memo_applied' => (float) $payment->memo_applied,
                'cash_out' => (float) $payment->cash_out,
                'deposit_total' => (float) $payment->deposit_total,
                'deposit_remaining' => (float) $payment->deposit_remaining,
                'failure_reason' => $payment->failure_reason,
                'memo' => $payment->memo,
            ],
            'allocations' => $payment->allocations->map(fn ($allocation): array => [
                'purchase_invoice_id' => (int) $allocation->purchase_invoice_id,
                'amount' => (float) $allocation->amount,
            ])->all(),
            'withholdings' => $payment->withholdings->map(fn ($withholding): array => [
                'account_id' => (int) $withholding->account_id,
                'type' => (string) $withholding->type,
                'value' => (float) $withholding->value,
                'amount' => (float) $withholding->amount,
            ])->all(),
            'deposit_uses' => $payment->depositUses->map(fn ($use): array => [
                'payment_id' => (int) $use->source_payment_id,
                'number' => (string) ($use->sourcePayment?->number ?? ''),
                'amount' => (float) $use->amount,
            ])->all(),
            'memo_uses' => $payment->memoUses->map(fn ($use): array => [
                'memo_id' => (int) $use->supplier_debit_memo_id,
                'amount' => (float) $use->amount,
            ])->all(),
            'journal' => $this->journals->execute('purchase_payment', (int) $payment->id),
            'tags' => $this->tagLabels($payment->id),
        ];
    }

    /**
     * Nama tag diambil dari master Purchasing (Application API), bukan dari
     * tabel tag, supaya batas modul tetap terjaga.
     *
     * @return list<array{id: int, name: string}>
     */
    private function tagLabels(int $paymentId): array
    {
        $ids = DB::table('purchase_payment_purchase_tag')
            ->where('purchase_payment_id', $paymentId)
            ->orderBy('purchase_tag_id')
            ->pluck('purchase_tag_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            return [];
        }

        return collect($this->purchaseTags->execute())
            ->filter(fn (array $tag): bool => in_array((int) $tag['id'], $ids, true))
            ->map(fn (array $tag): array => [
                'id' => (int) $tag['id'],
                'name' => (string) $tag['name'],
            ])
            ->values()
            ->all();
    }
}
