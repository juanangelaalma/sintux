<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\TaxQuery;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Tax;
use Tests\TestCase;

class TaxQueryTest extends TestCase
{
    private const SCHEMA_NAME = 'tax_query_test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->where('id', 'tax-query-tenant')->delete();
        $this->dropSchema();

        $this->tenant = Tenant::create([
            'id' => 'tax-query-tenant',
            'name' => 'Tax Query',
            'schema_name' => self::SCHEMA_NAME,
            'is_active' => true,
        ]);

        tenancy()->initialize($this->tenant);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropSchema();
        parent::tearDown();
    }

    public function test_lists_only_active_taxes_configured_for_each_transaction_type(): void
    {
        Tax::query()->delete();

        $account = ChartOfAccount::query()->firstOrFail();
        $purchaseTax = Tax::create([
            'code' => 'PURCHASE',
            'name' => 'Purchase tax',
            'rate' => '11.0000',
            'input_account_id' => $account->id,
            'output_account_id' => null,
            'is_active' => true,
        ]);
        $salesTax = Tax::create([
            'code' => 'SALE',
            'name' => 'Sales tax',
            'rate' => '12.0000',
            'input_account_id' => null,
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);
        Tax::create([
            'code' => 'INACTIVE',
            'name' => 'Inactive tax',
            'rate' => '13.0000',
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => false,
        ]);
        Tax::create([
            'code' => 'UNCONFIGURED',
            'name' => 'Unconfigured tax',
            'rate' => '14.0000',
            'input_account_id' => null,
            'output_account_id' => null,
            'is_active' => true,
        ]);

        $query = app(TaxQuery::class);

        $this->assertSame([$purchaseTax->id], array_column($query->listForPurchase(), 'id'));
        $this->assertSame([$salesTax->id], array_column($query->listForSale(), 'id'));
    }

    public function test_checks_tax_eligibility_for_each_transaction_type(): void
    {
        Tax::query()->delete();

        $account = ChartOfAccount::query()->firstOrFail();
        $purchaseTax = Tax::create([
            'code' => 'PURCHASE',
            'name' => 'Purchase tax',
            'rate' => '11.0000',
            'input_account_id' => $account->id,
            'is_active' => true,
        ]);
        $salesTax = Tax::create([
            'code' => 'SALE',
            'name' => 'Sales tax',
            'rate' => '12.0000',
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);
        $inactiveTax = Tax::create([
            'code' => 'INACTIVE',
            'name' => 'Inactive tax',
            'rate' => '13.0000',
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => false,
        ]);

        $query = app(TaxQuery::class);

        $this->assertTrue($query->isEligibleForPurchase($purchaseTax->id));
        $this->assertFalse($query->isEligibleForPurchase($salesTax->id));
        $this->assertTrue($query->isEligibleForSale($salesTax->id));
        $this->assertFalse($query->isEligibleForSale($purchaseTax->id));
        $this->assertFalse($query->isEligibleForPurchase($inactiveTax->id));
        $this->assertFalse($query->isEligibleForSale($inactiveTax->id));
        $this->assertFalse($query->isEligibleForPurchase(PHP_INT_MAX));
    }

    public function test_withholding_tax_is_hidden_from_transaction_lists(): void
    {
        Tax::query()->delete();

        $account = ChartOfAccount::query()->firstOrFail();
        Tax::create([
            'code' => 'PPH23',
            'name' => 'PPh 23',
            'rate' => '2.0000',
            'type' => Tax::TYPE_SINGLE,
            'is_withholding' => true,
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);

        $query = app(TaxQuery::class);

        $this->assertSame([], $query->listForPurchase());
        $this->assertSame([], $query->listForSale());
    }

    public function test_group_tax_with_all_eligible_members_is_listed_with_projection(): void
    {
        Tax::query()->delete();

        $account = ChartOfAccount::query()->firstOrFail();
        $sc = Tax::create([
            'code' => 'SC',
            'name' => 'Service Charge',
            'rate' => '5.0000',
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);
        $pb1 = Tax::create([
            'code' => 'PB1',
            'name' => 'PB1',
            'rate' => '10.0000',
            'dpp_multiplier' => true,
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);
        $group = Tax::create([
            'code' => 'GRUP-RESTO',
            'name' => 'Pajak Restoran',
            'rate' => '0',
            'type' => Tax::TYPE_GROUP,
            'is_active' => true,
        ]);

        $group->groupMembers()->create(['member_tax_id' => $pb1->id, 'position' => 2, 'is_compound' => true]);
        $group->groupMembers()->create(['member_tax_id' => $sc->id, 'position' => 1, 'is_compound' => false]);

        $query = app(TaxQuery::class);
        $rows = collect($query->listForSale())->keyBy('id');

        $this->assertSame([$group->id], $rows->where('type', Tax::TYPE_GROUP)->pluck('id')->all());
        $this->assertCount(3, $rows); // SC, PB1 (satuan) + grup
        $groupRow = $rows->get($group->id);
        $this->assertSame(Tax::TYPE_GROUP, $groupRow['type']);
        $this->assertEquals(15.0, $groupRow['rate']);
        // Urutan projection mengikuti position, bukan urutan insert.
        $this->assertSame([$sc->id, $pb1->id], array_column($groupRow['members'], 'id'));
        $this->assertTrue($groupRow['members'][1]['is_compound']);
        $this->assertTrue($groupRow['members'][1]['dpp_multiplier']);
        $this->assertTrue($query->isEligibleForPurchase($group->id));
    }

    public function test_group_with_withholding_or_inactive_member_is_hidden(): void
    {
        Tax::query()->delete();

        $account = ChartOfAccount::query()->firstOrFail();
        $ok = Tax::create([
            'code' => 'OK',
            'name' => 'OK tax',
            'rate' => '5.0000',
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);
        $withholding = Tax::create([
            'code' => 'PPH',
            'name' => 'PPh grup',
            'rate' => '2.0000',
            'is_withholding' => true,
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => true,
        ]);

        $groupWithPph = Tax::create(['code' => 'G-PPH', 'name' => 'Group PPh', 'rate' => '0', 'type' => Tax::TYPE_GROUP, 'is_active' => true]);
        $groupWithPph->groupMembers()->create(['member_tax_id' => $ok->id, 'position' => 1]);
        $groupWithPph->groupMembers()->create(['member_tax_id' => $withholding->id, 'position' => 2]);

        $groupInactive = Tax::create(['code' => 'G-INACT', 'name' => 'Group Inactive', 'rate' => '0', 'type' => Tax::TYPE_GROUP, 'is_active' => true]);
        $dead = Tax::create([
            'code' => 'DEAD',
            'name' => 'Dead tax',
            'rate' => '3.0000',
            'input_account_id' => $account->id,
            'output_account_id' => $account->id,
            'is_active' => false,
        ]);
        $groupInactive->groupMembers()->create(['member_tax_id' => $ok->id, 'position' => 1]);
        $groupInactive->groupMembers()->create(['member_tax_id' => $dead->id, 'position' => 2]);

        $query = app(TaxQuery::class);

        $this->assertNotContains($groupWithPph->id, array_column($query->listForSale(), 'id'));
        $this->assertNotContains($groupInactive->id, array_column($query->listForSale(), 'id'));
        $this->assertFalse($query->isEligibleForSale($groupWithPph->id));
        $this->assertFalse($query->isEligibleForPurchase($groupInactive->id));
    }

    public function test_empty_group_is_not_eligible(): void
    {
        Tax::query()->delete();

        $group = Tax::create(['code' => 'G-EMPTY', 'name' => 'Empty group', 'rate' => '0', 'type' => Tax::TYPE_GROUP, 'is_active' => true]);

        $query = app(TaxQuery::class);

        $this->assertSame([], $query->listForSale());
        $this->assertFalse($query->isEligibleForSale($group->id));
    }

    private function dropSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
    }
}
