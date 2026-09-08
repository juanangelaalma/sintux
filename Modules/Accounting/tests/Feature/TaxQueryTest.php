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

    private function dropSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
    }
}
