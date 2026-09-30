<?php

namespace Modules\Expense\Application\Expense;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Expense\Models\Expense;

/**
 * Nomor biaya otomatis (BR-04), mulai 10001 dan urut naik.
 *
 * Advisory lock tenantWide dengan hashtext, sama seperti
 * CreatePurchasePayment, supaya dua create bersamaan tidak mendapat
 * sequence yang sama. Columns `number` dan `sequence` dipisah: `sequence`
 * selalu numerik sehingga aman diurutkan, sementara `number` boleh berisi
 * teks bebas dari user tanpa merusak penomoran berikutnya.
 */
class GenerateExpenseNumber
{
    public const FIRST_SEQUENCE = 10001;

    /**
     * @return array{number: string, sequence: int}
     */
    public function execute(): array
    {
        return DB::transaction(function (): array {
            DB::statement("SELECT pg_advisory_xact_lock(hashtext('expense_number'))");

            $sequence = (int) (Expense::query()->max('sequence') ?? 0) + 1;
            $sequence = max($sequence, self::FIRST_SEQUENCE);

            $this->assertNumberIsFree((string) $sequence);

            return ['number' => (string) $sequence, 'sequence' => $sequence];
        });
    }

    /**
     * Nomor yang diinput user harus unik per tenant. Sequence auto selalu
     * lolos karena angka 10001 ke atas tidak mungkin bentrok dengan format
     * custom yang mengandung spasi atau huruf.
     */
    public function assertNumberIsFree(string $number): void
    {
        $exists = Expense::withTrashed()->where('number', $number)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'number' => 'Nomor biaya sudah dipakai. Gunakan nomor lain.',
            ]);
        }
    }
}
