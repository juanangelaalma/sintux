<?php

namespace Modules\Payment\Application;

use Modules\Payment\Models\PaymentMethod;

/**
 * Daftar cara pembayaran aktif. Public API milik Payment supaya modul lain
 * (mis. Expense) tidak perlu meng-import PaymentMethod secara langsung.
 */
class GetPaymentMethods
{
    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    public function execute(): array
    {
        return PaymentMethod::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn (PaymentMethod $method): array => [
                'id' => (int) $method->id,
                'name' => (string) $method->name,
                'code' => (string) $method->code,
            ])
            ->values()
            ->all();
    }
}
