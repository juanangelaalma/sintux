<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\Tax\CreateTax;
use Modules\Accounting\Application\Tax\DeleteTax;
use Modules\Accounting\Application\Tax\GetTaxes;
use Modules\Accounting\Application\Tax\TaxReferences;
use Modules\Accounting\Application\Tax\UpdateTax;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Tax;
use Tests\TestCase;

class TaxCrudTest extends TestCase
{
    private const SCHEMA_NAME = 'tax_crud_test';

    private Tenant $tenant;

    private int $accountA;

    private int $accountB;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->where('id', 'tax-crud-tenant')->delete();
        $this->dropSchema();

        $this->tenant = Tenant::create([
            'id' => 'tax-crud-tenant',
            'name' => 'Tax Crud',
            'schema_name' => self::SCHEMA_NAME,
            'is_active' => true,
        ]);

        tenancy()->initialize($this->tenant);
        $this->seed(ChartOfAccountsSeeder::class);

        [$this->accountA, $this->accountB] = ChartOfAccount::query()
            ->where('is_header', false)
            ->orderBy('id')
            ->limit(2)
            ->pluck('id')
            ->all();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropSchema();
        parent::tearDown();
    }

    public function test_create_single_tax(): void
    {
        $tax = app(CreateTax::class)->execute([
            'type' => 'single',
            'name' => 'PPN 12',
            'code' => 'PPN12',
            'rate' => 12,
            'output_account_id' => $this->accountA,
            'input_account_id' => $this->accountB,
        ]);

        $this->assertSame(Tax::TYPE_SINGLE, $tax->type);
        $this->assertEquals(12.0, (float) $tax->rate);
        $this->assertTrue($tax->is_active);
    }

    public function test_create_single_rejects_no_account_and_conflicting_flags_and_duplicates(): void
    {
        $this->expectException(ValidationException::class);
        app(CreateTax::class)->execute([
            'type' => 'single', 'name' => 'Tanpa Akun', 'code' => 'NOACC', 'rate' => 5,
        ]);
    }

    public function test_create_single_rejects_withholding_with_multiplier(): void
    {
        $this->expectException(ValidationException::class);
        app(CreateTax::class)->execute([
            'type' => 'single',
            'name' => 'Bahkan',
            'code' => 'BAHAN',
            'rate' => 5,
            'is_withholding' => true,
            'dpp_multiplier' => true,
            'output_account_id' => $this->accountA,
        ]);
    }

    public function test_create_rejects_duplicate_name_and_code(): void
    {
        $create = app(CreateTax::class);
        $create->execute([
            'type' => 'single', 'name' => 'Duplikan', 'code' => 'DUP', 'rate' => 10, 'output_account_id' => $this->accountA,
        ]);

        $this->expectException(ValidationException::class);
        $create->execute([
            'type' => 'single', 'name' => 'Duplikan', 'code' => 'DUP2', 'rate' => 10, 'output_account_id' => $this->accountA,
        ]);
    }

    public function test_create_group_stores_ordered_members_and_derived_rate(): void
    {
        $sc = $this->makeSingle('Service Charge', 'SC', 5);
        $pb1 = $this->makeSingle('PB1', 'PB1', 10);

        $group = app(CreateTax::class)->execute([
            'type' => 'group',
            'name' => 'Pajak Restoran',
            'code' => 'GRESTO',
            'members' => [
                ['id' => $pb1->id, 'is_compound' => true],
                ['id' => $sc->id, 'is_compound' => false],
            ],
        ]);

        $rows = DB::table('tax_group_members')->where('tax_group_id', $group->id)->orderBy('position')->get();

        $this->assertSame([$pb1->id, $sc->id], $rows->pluck('member_tax_id')->all());
        $this->assertTrue((bool) $rows[0]->is_compound);
        $this->assertEquals(15.0, (float) $group->fresh()->rate);
    }

    public function test_group_rejects_nesting_and_compound_with_multiplier_and_inactive(): void
    {
        $group = app(CreateTax::class)->execute([
            'type' => 'group', 'name' => 'Grup Asal', 'code' => 'GASAL',
            'members' => [['id' => $this->makeSingle('A', 'A', 1)->id]],
        ]);

        // No nesting.
        try {
            app(CreateTax::class)->execute([
                'type' => 'group', 'name' => 'Grup Bersarang', 'code' => 'GBERSAR',
                'members' => [['id' => $group->id]],
            ]);
            $this->fail('Nesting grup harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('members', $e->errors());
        }

        // Majemuk + anggota multiplier ditolak.
        $multiplier = $this->makeSingle('Dengan Pengali', 'DPNGALI', 12, multiplier: true);
        $service = $this->makeSingle('Jasa', 'JASA', 5);

        $this->expectException(ValidationException::class);
        app(CreateTax::class)->execute([
            'type' => 'group', 'name' => 'Grup Campur', 'code' => 'GCAMPUR',
            'members' => [['id' => $multiplier->id], ['id' => $service->id, 'is_compound' => true]],
        ]);
    }

    public function test_references_cover_group_membership_but_not_own_definition(): void
    {
        $single = $this->makeSingle('Dipakai Grup', 'DG', 3);
        app(CreateTax::class)->execute([
            'type' => 'group', 'name' => 'Grup Pemakai', 'code' => 'GP',
            'members' => [['id' => $single->id]],
        ]);

        $refs = app(TaxReferences::class);

        // Anggota grup = terpakai.
        $this->assertTrue($refs->inUse($single->id));

        // Grup itu sendiri belum dipakai dokumen -> boleh hapus.
        $groupId = Tax::query()->where('code', 'GP')->value('id');
        $this->assertFalse($refs->inUse((int) $groupId));
    }

    public function test_in_use_tax_locks_fields_and_blocks_delete(): void
    {
        $single = $this->makeSingle('Terikat Faktur', 'TF', 11);

        // Referensi nyata: baris faktur penjualan menunjuk pajak ini.
        // (Test guard FK lintas modul memang perlu menulis tabel tujuan;
        // pola DB::table mengikuti konvensi fixture repo ini.)
        $branchId = DB::table('branches')->insertGetId([
            'name' => 'T', 'code' => 'TT'.uniqid(), 'is_active' => true, 'is_headquarters' => false,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $customerId = DB::table('contacts')->insertGetId([
            'type' => 'customer', 'name' => 'C', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $salespersonId = DB::table('contacts')->insertGetId([
            'type' => 'employee', 'name' => 'S', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId, 'code' => 'W'.uniqid(), 'name' => 'W',
            'warehouse_type' => 'regular', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Kat '.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Pcs', 'code' => 'PCS'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'code' => 'P'.uniqid(), 'name' => 'p', 'category_id' => $categoryId, 'uom_id' => $uomId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId, 'branch_id' => $branchId, 'sku' => 'SKU'.uniqid(), 'variant_name' => 'v',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('sales_invoices')->insertGetId([
            'number' => 'SI-TEST-'.uniqid(), 'branch_id' => $branchId, 'customer_id' => $customerId,
            'customer_name' => 'X', 'transaction_type' => 'regular', 'warehouse_id' => $warehouseId,
            'warehouse_code' => 'W', 'warehouse_name' => 'W', 'salesperson_id' => $salespersonId,
            'salesperson_name' => 'S', 'invoice_date' => '2026-09-15',
            'status' => 'approved', 'currency_code' => 'IDR',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sales_invoice_items')->insert([
            'sales_invoice_id' => $invoiceId, 'product_variant_id' => $variantId, 'product_name' => 'p',
            'sku' => 'p', 'qty' => 1, 'unit_price' => 1, 'tax_id' => $single->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $update = app(UpdateTax::class);

        // Nama boleh; rate/tidak.
        $update->execute($single->id, ['name' => 'Terikat Faktur Revisi', 'rate' => (float) $single->rate]);
        $this->assertSame('Terikat Faktur Revisi', $single->fresh()->name);

        try {
            $update->execute($single->id, ['rate' => 99]);
            $this->fail('Ubah rate pajak terpakai harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('rate', $e->errors());
        }

        try {
            app(DeleteTax::class)->execute($single->id);
            $this->fail('Hapus pajak terpakai harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('sales invoice items', $e->getMessage());
        }
    }

    public function test_update_free_tax_full_edit_and_member_resync(): void
    {
        $a = $this->makeSingle('Bebas A', 'BA', 5);
        $b = $this->makeSingle('Bebas B', 'BB', 10);
        $group = app(CreateTax::class)->execute([
            'type' => 'group', 'name' => 'Grup Bebas', 'code' => 'GB',
            'members' => [['id' => $a->id], ['id' => $b->id]],
        ]);

        app(UpdateTax::class)->execute($group->id, [
            'type' => 'group', 'name' => 'Grup Bebas', 'code' => 'GB',
            'members' => [['id' => $b->id, 'is_compound' => true]],
        ]);

        $rows = DB::table('tax_group_members')->where('tax_group_id', $group->id)->get();
        $this->assertCount(1, $rows);
        $this->assertSame($b->id, (int) $rows[0]->member_tax_id);
        $this->assertTrue((bool) $rows[0]->is_compound);
        $this->assertEquals(10.0, (float) $group->fresh()->rate);

        $update = app(UpdateTax::class);
        $update->execute($a->id, [
            'type' => 'single', 'name' => 'Bebas A', 'code' => 'BA', 'rate' => 7,
            'input_account_id' => $this->accountA, 'output_account_id' => $this->accountB,
        ]);
        $this->assertEquals(7.0, (float) $a->fresh()->rate);
    }

    public function test_get_taxes_reports_in_use_and_member_projection(): void
    {
        $single = $this->makeSingle('Lapor A', 'LA', 5);
        $group = app(CreateTax::class)->execute([
            'type' => 'group', 'name' => 'Lapor Grup', 'code' => 'LG',
            'members' => [['id' => $single->id, 'is_compound' => true]],
        ]);

        $rows = collect(app(GetTaxes::class)->execute())->keyBy('code');

        $this->assertTrue($rows->get('LA')['in_use']); // anggota grup
        $this->assertFalse($rows->get('LG')['in_use']);
        $this->assertEquals(5.0, $rows->get('LG')['rate']);
        $this->assertSame([$single->id], array_column($rows->get('LG')['members'], 'id'));

        $detail = app(GetTaxes::class)->find($group->id);
        $this->assertTrue($detail['members'][0]['is_compound']);
    }

    private function makeSingle(string $name, string $code, float $rate, bool $multiplier = false): Tax
    {
        return app(CreateTax::class)->execute([
            'type' => 'single',
            'name' => $name,
            'code' => $code,
            'rate' => $rate,
            'dpp_multiplier' => $multiplier,
            'output_account_id' => $this->accountA,
            'input_account_id' => $this->accountB,
        ]);
    }

    private function dropSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
    }
}
