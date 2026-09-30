<?php

namespace Modules\Expense\Tests\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Expense\Models\Expense;

/**
 * Fixture bersama untuk test Expense.
 *
 * Satu tenant dibuat SEKALI per proses test, bukan sekali per test. Suite
 * lama membuat 51 schema tenant dan menjalankan `tenants:migrate` penuh 51
 * kali, yang memperpanjang jendela lock di database `testing` dan memicu
 * deadlock ketika modul lain jalankan test bersamaan. Satu tenant per proses
 * memangkas biaya itu jadi satu kali.
 *
 * Test mengisolate data antar test lewat reset(): truncate tabel transaksi
 * lalu seed ulang referensi yang dibutuhkannya.
 */
class ExpenseFixture
{
    private static ?Tenant $tenant = null;

    /** @var array<string, mixed>|null */
    private static ?array $context = null;

    /**
     * Siapkan tenant sekali, lalu bersihkan data transaksi. Dipanggil dari
     * setUp() setiap test.
     *
     * @return array<string, mixed>
     */
    public static function prepare(): array
    {
        $context = self::context();
        self::reset();
        self::initialize();

        return $context;
    }

    public static function initialize(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        tenancy()->initialize(self::tenant());
    }

    public static function tenant(): Tenant
    {
        if (! self::$tenant instanceof Tenant) {
            self::$tenant = self::createTenant();
        }

        return self::$tenant;
    }

    public static function tenantId(): string
    {
        return (string) self::tenant()->id;
    }

    public static function schemaName(): string
    {
        return (string) self::tenant()->schema_name;
    }

    /**
     * Bersihkan sisa schema dan tenant setelah test terakhir di proses.
     */
    public static function destroy(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if (self::$tenant instanceof Tenant) {
            self::dropSchema(self::schemaName());
            DB::table('tenants')->where('id', self::tenantId())->delete();
            self::$tenant = null;
            self::$context = null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function context(): array
    {
        if (self::$context !== null) {
            return self::$context;
        }

        $tenant = self::tenant();

        tenancy()->initialize($tenant);

        try {
            $branchId = (int) DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $charts = app(ChartOfAccountQuery::class);

            // Akun diambil lewat public API Accounting, bukan lewat kode CoA
            // yang bisa berubah kalau seeder diperbarui. Sekaligus memverifikasi
            // bahwa listForExpense() dan listCashAndBank() benar.
            $expenseAccounts = $charts->listForExpense();
            $cashAccounts = $charts->listCashAndBank();

            // PPN default tenant baru sudah ter-map ke PPN Masukan.
            $tax = DB::table('taxes')
                ->whereNotNull('input_account_id')
                ->orderBy('id')
                ->first();

            return self::$context = [
                'tenant' => $tenant,
                'tenantId' => $tenant->id,
                'schemaName' => self::schemaName(),
                'branchId' => $branchId,
                'expenseAccountId' => (int) $expenseAccounts[0]['id'],
                'expenseAccountIds' => array_map(
                    fn (array $account): int => (int) $account['id'],
                    $expenseAccounts,
                ),
                'cashAccountId' => (int) $cashAccounts[0]['id'],
                'revenueAccountId' => self::firstLeafAccountId('REVENUE'),
                'payableAccountId' => (int) $charts->findBySeedKey('accounting.coa.2101')['id'],
                'inputTaxAccountId' => (int) $charts->findBySeedKey('accounting.coa.1404')['id'],
                'taxId' => (int) $tax->id,
                'taxRate' => (float) $tax->rate,
                'dppMultiplier' => (bool) $tax->dpp_multiplier,
                'supplierId' => self::seedContact($branchId, 'supplier'),
                'employeeId' => self::seedContact($branchId, 'employee'),
                'paymentMethodId' => (int) DB::table('payment_methods')->orderBy('id')->value('id'),
            ];
        } finally {
            tenancy()->end();
        }
    }

    private static function createTenant(): Tenant
    {
        $id = 'expenses_test';
        $schemaName = 'sch_'.$id;

        // Run sebelumnya bisa menyisakan baris central dan schema-nya.
        // Bersihkan keduanya supaya fixture ini idempoten dan bisa dijalankan
        // berulang tanpa perlu reset database manual.
        self::dropSchema($schemaName);

        try {
            DB::table('tenants')->where('id', $id)->delete();
        } catch (\Throwable) {
            // Tabel tenants belum ada di database pertama.
        }

        return Tenant::query()->create([
            'id' => $id,
            'name' => 'Expense Test',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);
    }

    /**
     * Truncate tabel transaksi Expense dan jurnal supaya setiap test mulai
     * dari kondisi bersih tanpa perlu tenant baru. Tag disemun ulang karena
     * baris awalnya dibuat oleh migrasi.
     */
    private static function reset(): void
    {
        tenancy()->initialize(self::tenant());

        DB::statement(
            'TRUNCATE expense_attachments, expense_lines, expense_expense_tag, expenses, journals, journal_lines RESTART IDENTITY CASCADE'
        );

        // Cabang tambahan yang dibuat test harus hilang, tapi cabang utama
        // yang jadi fixture dan kontak yang merujuk padanya harus tetap ada.
        DB::table('branches')->where('code', '!=', 'HQ')->delete();

        DB::table('expense_tags')->delete();

        $now = now();

        DB::table('expense_tags')->insert([
            ['name' => 'Operasional', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Utilitas', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    private static function seedContact(int $branchId, string $type): int
    {
        $existing = DB::table('contacts')
            ->where('branch_id', $branchId)
            ->where('type', $type)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        return (int) DB::table('contacts')->insertGetId([
            'branch_id' => $branchId,
            'type' => $type,
            'name' => ucfirst($type).' Default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Akun non-header pertama bertipe tertentu. Dipakai test untuk
     * membuktikan BR-02 menolak akun di luar tipe beban.
     */
    public static function firstLeafAccountId(string $accountTypeCode): int
    {
        return (int) DB::table('chart_of_accounts')
            ->join('coa_account_categories', 'coa_account_categories.id', '=', 'chart_of_accounts.account_category_id')
            ->join('coa_account_types', 'coa_account_types.id', '=', 'coa_account_categories.account_type_id')
            ->where('chart_of_accounts.is_header', false)
            ->whereNull('chart_of_accounts.deleted_at')
            ->where('coa_account_types.code', $accountTypeCode)
            ->orderBy('chart_of_accounts.code')
            ->value('chart_of_accounts.id');
    }

    /**
     * Data form minimal yang valid: satu baris akun, bayar langsung, tanpa
     * pemotongan.
     *
     * @param  array<string, mixed>  $ctx
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(array $ctx, array $overrides = []): array
    {
        return array_merge([
            'pay_from_account_id' => $ctx['cashAccountId'],
            'is_pay_later' => false,
            'contact_id' => $ctx['supplierId'],
            'transaction_date' => '2026-09-15',
            'payment_method_id' => $ctx['paymentMethodId'],
            'number' => null,
            'tag_ids' => [],
            'billing_address' => null,
            'is_tax_inclusive' => false,
            'memo' => null,
            'withholding' => null,
            'lines' => [
                [
                    'account_id' => $ctx['expenseAccountId'],
                    'description' => 'Beban operasional',
                    'tax_id' => $ctx['taxId'],
                    'amount' => 100000,
                ],
            ],
        ], $overrides);
    }

    public static function dropSchema(string $schemaName): void
    {
        try {
            DB::statement('DROP SCHEMA IF EXISTS "'.str_replace('"', '""', $schemaName).'" CASCADE');
        } catch (\Throwable) {
            // Schema sudah tidak ada.
        }
    }

    public static function cleanupCentralTables(): void
    {
        foreach (['company_user_branches', 'company_users', 'users'] as $table) {
            try {
                DB::table($table)->delete();
            } catch (\Throwable) {
                // Tabel belum ada atau sedang terkunci.
            }
        }
    }

    public static function countExpenses(): int
    {
        return Expense::query()->count();
    }
}
