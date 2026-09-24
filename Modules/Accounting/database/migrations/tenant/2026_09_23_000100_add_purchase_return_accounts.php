<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill akun retur pembelian untuk tenant yang sudah migrate
     * sebelum akun ini ada di seeder. Resolusi identitas via seed_key
     * (accounting.coa.1398 / accounting.coa.5201); kolom code mengikuti
     * kode legacy agar mudah dikenali di laporan.
     *
     * @var array<string, array{category: string, code: string, name: string, description: string}>
     */
    private array $accounts = [
        'accounting.coa.1398' => [
            'category' => 'INVENTORY',
            'code' => '1398',
            'name' => 'Penyesuaian Persediaan Barang',
            'description' => 'Selisih biaya FIFO vs harga DO saat retur pembelian.',
        ],
        'accounting.coa.5201' => [
            'category' => 'COST_OF_REVENUE',
            'code' => '5201',
            'name' => 'Beban Pembelian',
            'description' => 'Beban pembelian untuk produk tanpa pelacakan persediaan.',
        ],
    ];

    public function up(): void
    {
        foreach ($this->accounts as $seedKey => $spec) {
            $exists = DB::table('chart_of_accounts')->where('seed_key', $seedKey)->exists();

            if ($exists) {
                continue;
            }

            $category = DB::table('coa_account_categories')->where('code', $spec['category'])->first();

            if (! $category) {
                continue;
            }

            $parent = DB::table('chart_of_accounts')
                ->where('account_category_id', $category->id)
                ->where('is_header', true)
                ->orderBy('id')
                ->first();

            $codeTaken = DB::table('chart_of_accounts')->where('code', $spec['code'])->exists();
            $code = $codeTaken ? $this->fallbackCode($spec['code']) : $spec['code'];

            DB::table('chart_of_accounts')->insert([
                'seed_key' => $seedKey,
                'code' => $code,
                'parent_id' => $parent->id ?? null,
                'account_category_id' => $category->id,
                'name' => $spec['name'],
                'description' => $spec['description'],
                'is_header' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('chart_of_accounts')
            ->whereIn('seed_key', array_keys($this->accounts))
            ->delete();
    }

    private function fallbackCode(string $code): string
    {
        return $code.'-R';
    }
};
