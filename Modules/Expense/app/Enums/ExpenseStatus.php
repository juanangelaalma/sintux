<?php

namespace Modules\Expense\Enums;

/**
 * Fase 1 hanya punya dua status. Kolom `expenses.status` tetap string(20)
 * supaya `draft` (Fase 3 approval) dan `overdue` bisa ditambahkan tanpa
 * migrasi. Lihat docs/expense/OPEN_QUESTIONS.md Q-05 dan Q-24.
 */
enum ExpenseStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::Closed => 'Closed',
        };
    }

    /**
     * Biaya lunas. Kartu ringkasan "bulan ini" dan "30 hari terakhir"
     * hanya menghitung status ini.
     */
    public function isSettled(): bool
    {
        return $this === self::Closed;
    }
}
