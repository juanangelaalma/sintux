<?php

namespace Modules\Payment\Application\Deposit;

use Modules\Payment\Models\PurchasePayment;

/**
 * Saldo uang muka per supplier untuk form pembayaran (dipakai juga
 * validasi apply). Public API milik Payment.
 *
 * @return list<array{id: int, number: string, remaining: float}>
 */
class GetSupplierDeposits
{
    public function execute(int $supplierId): array
    {
        return PurchasePayment::query()
            ->where('supplier_id', $supplierId)
            ->where('mode', 'deposit')
            ->where('deposit_remaining', '>', 0)
            ->orderBy('id')
            ->get(['id', 'number', 'deposit_remaining'])
            ->map(fn (PurchasePayment $payment): array => [
                'id' => (int) $payment->id,
                'number' => (string) $payment->number,
                'remaining' => (float) $payment->deposit_remaining,
            ])
            ->all();
    }
}
