<?php

namespace Modules\Payment\Application\PurchasePayment;

use Modules\Payment\Models\PurchasePayment;

class GetInvoicePayments
{
    /**
     * Histori pembayaran yang dialokasikan ke satu faktur.
     *
     * @param  list<int>  $branchIds
     * @return list<array{
     *     id: int, number: string, payment_date: string,
     *     payment_method: string|null, status: string, amount: float,
     *     memo: string|null
     * }>
     */
    public function execute(int $invoiceId, array $branchIds = []): array
    {
        return PurchasePayment::with([
            'paymentMethod',
            'allocations' => fn ($query) => $query->where('purchase_invoice_id', $invoiceId),
        ])
            ->whereHas('allocations', fn ($query) => $query->where('purchase_invoice_id', $invoiceId))
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (PurchasePayment $payment): array => [
                'id' => (int) $payment->id,
                'number' => (string) $payment->number,
                'payment_date' => $payment->payment_date->toDateString(),
                'payment_method' => $payment->paymentMethod?->name,
                'status' => (string) $payment->status,
                'amount' => (float) $payment->allocations->sum('amount'),
                'memo' => $payment->memo,
            ])
            ->all();
    }
}
