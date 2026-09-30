<?php

namespace Modules\Expense\Enums;

/**
 * Asal transaksi biaya. Fase 1 hanya menulis `manual`; nilai lain
 * disiapkan supaya BR-14 dan BR-17 bisa memblokir ubah dan hapus begitu
 * integrasi Fase 4 tiba tanpa perubahan skema.
 */
enum ExpenseSource: string
{
    case Manual = 'manual';
    case Import = 'import';
    case MekariExpense = 'mekari_expense';
    case MekariPay = 'mekari_pay';

    /**
     * Sumber yang datanya dimiliki sistem lain, sehingga transaksi harus
     * diperlakukan read-only (BR-14) dan tidak boleh dihapus (BR-17).
     */
    public function isExternal(): bool
    {
        return $this === self::MekariExpense;
    }
}
