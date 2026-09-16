<?php

namespace Modules\Accounting\Application\Tax;

use Illuminate\Support\Facades\DB;

/**
 * Menemukan referensi ke sebuah pajak TANPA hardcode tabel modul lain:
 * FK yang menunjuk taxes.id dibaca dari information_schema, sehingga tabel
 * baru dengan kolom tax_id otomatis ikut terjaga tanpa perubahan kode.
 *
 * ponytail: daftar relasi `tax_group_members.tax_group_id` dikecualikan
 * (itu definisi grup itu sendiri, bukan pemakaian). Naikkan ke query UNION
 * tunggal bila jumlah pajak membuat loop per-tabel terasa lambat.
 */
final class TaxReferences
{
    /** Relasi internal grup -> anggotanya; bukan "pemakaian". */
    private const EXCLUDED = [
        'tax_group_members' => ['tax_group_id'],
    ];

    /**
     * @return list<array{table: string, column: string}>
     */
    public function referrers(): array
    {
        $rows = DB::select(
            "SELECT tc.table_name AS src_table, kcu.column_name AS src_column
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON kcu.constraint_name = tc.constraint_name
              AND kcu.table_schema = tc.table_schema
             JOIN information_schema.constraint_column_usage ccu
               ON ccu.constraint_name = tc.constraint_name
              AND ccu.table_schema = tc.table_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_schema = current_schema()
               AND ccu.table_name = 'taxes'
               AND ccu.column_name = 'id'
             ORDER BY tc.table_name, kcu.column_name"
        );

        $referrers = [];

        foreach ($rows as $row) {
            $excluded = self::EXCLUDED[$row->src_table] ?? [];

            if (in_array($row->src_column, $excluded, true)) {
                continue;
            }

            $referrers[] = ['table' => $row->src_table, 'column' => $row->src_column];
        }

        return $referrers;
    }

    /**
     * Tabel+kolom yang benar-benar memakai pajak ini.
     *
     * @return list<array{table: string, column: string}>
     */
    public function usage(int $taxId): array
    {
        $used = [];

        foreach ($this->referrers() as $referrer) {
            if (DB::table($referrer['table'])->where($referrer['column'], $taxId)->exists()) {
                $used[] = $referrer;
            }
        }

        return $used;
    }

    public function inUse(int $taxId): bool
    {
        foreach ($this->referrers() as $referrer) {
            if (DB::table($referrer['table'])->where($referrer['column'], $taxId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Label manusiawi untuk pesan validasi.
     *
     * @param  list<array{table: string, column: string}>  $usage
     */
    public function describe(array $usage): string
    {
        $labels = array_values(array_unique(array_map(
            fn (array $referrer): string => $this->label($referrer['table']),
            $usage
        )));

        return implode(', ', $labels);
    }

    private function label(string $table): string
    {
        return match ($table) {
            'tax_group_members' => 'anggota pajak grup',
            'products' => 'produk',
            'chart_of_accounts' => 'akun CoA',
            default => str_replace('_', ' ', $table),
        };
    }
}
