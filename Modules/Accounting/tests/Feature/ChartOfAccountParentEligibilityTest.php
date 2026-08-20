<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\CanBecomeChartOfAccountParent;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\CreateChartOfAccount;
use Modules\Accounting\Application\GetChartOfAccounts;
use Modules\Accounting\Application\UpdateChartOfAccount;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class ChartOfAccountParentEligibilityTest extends TestCase
{
    private const SCHEMA_NAME = 'chart_of_account_parent_eligibility_test';

    private const USER_EMAIL = 'chart-of-account-parent-eligibility@example.test';

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('tenants')->where('id', 'chart-of-account-parent-eligibility-tenant')->delete();
        DB::table('users')->where('email', self::USER_EMAIL)->delete();
        $this->dropSchema();

        $this->seed(RolePermissionSeeder::class);

        $this->tenant = Tenant::create([
            'id' => 'chart-of-account-parent-eligibility-tenant',
            'name' => 'Chart of Account Parent Eligibility',
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

    public function test_an_active_non_header_account_is_an_eligible_parent(): void
    {
        $account = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        $this->assertTrue(
            app(CanBecomeChartOfAccountParent::class)->execute($account),
        );
    }

    public function test_it_identifies_accounts_eligible_for_product_references(): void
    {
        $eligible = ChartOfAccount::query()->where('is_header', false)->firstOrFail();
        $header = ChartOfAccount::query()->where('is_header', true)->firstOrFail();
        $deleted = ChartOfAccount::query()
            ->where('is_header', false)
            ->whereKeyNot($eligible->id)
            ->firstOrFail();
        $deleted->delete();

        $query = app(ChartOfAccountQuery::class);

        $this->assertTrue($query->isEligible($eligible->id));
        $this->assertFalse($query->isEligible($header->id));
        $this->assertFalse($query->isEligible($deleted->id));
        $this->assertFalse($query->isEligible(PHP_INT_MAX));
    }

    public function test_parent_candidates_include_active_non_header_accounts(): void
    {
        $account = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        $parentIds = collect(app(GetChartOfAccounts::class)->execute()['parentAccounts'])
            ->pluck('id')
            ->all();

        $this->assertContains($account->id, $parentIds);
    }

    public function test_creating_a_sub_account_promotes_a_non_header_parent(): void
    {
        $parent = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        $child = app(CreateChartOfAccount::class)->execute([
            'account_category_id' => $parent->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $parent->id,
            'header_account_ids' => [],
            'default_tax_id' => null,
            'code' => $parent->code.'01',
            'name' => 'Child account',
            'description' => null,
            'is_header' => false,
        ]);

        $this->assertSame($parent->id, $child->parent_id);
        $this->assertTrue($parent->refresh()->is_header);
    }

    public function test_updating_a_sub_account_promotes_a_non_header_parent(): void
    {
        [$parent, $child] = ChartOfAccount::query()
            ->where('is_header', false)
            ->where('account_category_id', ChartOfAccount::query()->where('is_header', false)->value('account_category_id'))
            ->take(2)
            ->get()
            ->values();

        app(UpdateChartOfAccount::class)->execute($child, [
            'account_category_id' => $child->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $parent->id,
            'header_account_ids' => [],
            'default_tax_id' => $child->default_tax_id,
            'code' => $child->code,
            'name' => $child->name,
            'description' => $child->description,
            'is_header' => false,
        ]);

        $this->assertTrue($parent->refresh()->is_header);
        $this->assertSame($parent->id, $child->refresh()->parent_id);
    }

    public function test_updating_a_header_with_children_to_a_sub_account_retains_its_header_status(): void
    {
        $header = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        $child = app(CreateChartOfAccount::class)->execute([
            'account_category_id' => $header->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $header->id,
            'header_account_ids' => [],
            'default_tax_id' => null,
            'code' => $header->code.'01',
            'name' => 'Existing header child',
            'description' => null,
            'is_header' => false,
        ]);
        $parent = ChartOfAccount::query()
            ->where('is_header', false)
            ->where('account_category_id', $header->account_category_id)
            ->whereKeyNot([$header->id, $child->id])
            ->firstOrFail();

        app(UpdateChartOfAccount::class)->execute($header, [
            'account_category_id' => $header->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $parent->id,
            'header_account_ids' => [],
            'default_tax_id' => $header->default_tax_id,
            'code' => $header->code,
            'name' => $header->name,
            'description' => $header->description,
            'is_header' => false,
        ]);

        $this->assertTrue($header->refresh()->is_header);
        $this->assertSame($parent->id, $header->parent_id);
        $this->assertSame($header->id, $child->refresh()->parent_id);
    }

    public function test_updating_a_header_with_children_to_a_sub_account_via_http_retains_its_header_status(): void
    {
        $header = ChartOfAccount::query()->where('is_header', false)->firstOrFail();
        $child = app(CreateChartOfAccount::class)->execute([
            'account_category_id' => $header->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $header->id,
            'header_account_ids' => [],
            'default_tax_id' => null,
            'code' => $header->code.'01',
            'name' => 'HTTP regression child',
            'description' => null,
            'is_header' => false,
        ]);
        $parent = ChartOfAccount::query()
            ->where('is_header', false)
            ->where('account_category_id', $header->account_category_id)
            ->whereKeyNot([$header->id, $child->id])
            ->firstOrFail();

        $branchId = CompanyTestFixture::branch($this->tenant, [
            'name' => 'Headquarters',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
        $user = app(CreateCompanyUser::class)->execute($this->tenant->id, [
            'name' => 'Accounting Test User',
            'email' => self::USER_EMAIL,
            'password' => 'password',
            'company_role' => 'owner',
            'branch_id' => $branchId,
            'scope' => 'branch',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        session(['active_tenant_id' => $this->tenant->id, 'active_branch_id' => $branchId]);

        $this->actingAs($user)
            ->put(route('accounting.chart-of-accounts.update', $header), [
                'account_category_id' => $header->account_category_id,
                'detail_type' => 'sub_account',
                'parent_id' => $parent->id,
                'code' => $header->code,
                'name' => $header->name,
                'description' => $header->description,
                'default_tax_id' => $header->default_tax_id,
            ])
            ->assertRedirect(route('accounting.chart-of-accounts.index'));

        tenancy()->initialize($this->tenant);

        $movedAccount = $header->refresh();

        $this->assertTrue($movedAccount->is_header);
        $this->assertSame($parent->id, $movedAccount->parent_id);
        $this->assertSame($movedAccount->id, $child->refresh()->parent_id);
    }

    public function test_rejects_a_descendant_as_an_updated_parent(): void
    {
        $ancestor = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        $descendant = app(CreateChartOfAccount::class)->execute([
            'account_category_id' => $ancestor->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $ancestor->id,
            'header_account_ids' => [],
            'default_tax_id' => null,
            'code' => $ancestor->code.'01',
            'name' => 'Descendant account',
            'description' => null,
            'is_header' => false,
        ]);

        try {
            app(UpdateChartOfAccount::class)->execute($ancestor, [
                'account_category_id' => $ancestor->account_category_id,
                'detail_type' => 'sub_account',
                'parent_id' => $descendant->id,
                'header_account_ids' => [],
                'default_tax_id' => $ancestor->default_tax_id,
                'code' => $ancestor->code,
                'name' => $ancestor->name,
                'description' => $ancestor->description,
                'is_header' => false,
            ]);

            $this->fail('Updating an account under its descendant should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
            $this->assertSame(
                'Akun induk tidak valid atau akan membuat siklus hierarki.',
                $exception->errors()['parent_id'][0],
            );
        }
    }

    public function test_rejects_an_account_as_its_own_updated_parent(): void
    {
        $account = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        try {
            app(UpdateChartOfAccount::class)->execute($account, [
                'account_category_id' => $account->account_category_id,
                'detail_type' => 'sub_account',
                'parent_id' => $account->id,
                'header_account_ids' => [],
                'default_tax_id' => $account->default_tax_id,
                'code' => $account->code,
                'name' => $account->name,
                'description' => $account->description,
                'is_header' => false,
            ]);

            $this->fail('Updating an account under itself should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
            $this->assertSame(
                'Akun induk tidak valid atau akan membuat siklus hierarki.',
                $exception->errors()['parent_id'][0],
            );
        }
    }

    public function test_rejects_an_archived_account_as_an_updated_parent(): void
    {
        [$parent, $child] = ChartOfAccount::query()
            ->where('is_header', false)
            ->where('account_category_id', ChartOfAccount::query()->where('is_header', false)->value('account_category_id'))
            ->take(2)
            ->get()
            ->values();

        $parent->delete();

        try {
            app(UpdateChartOfAccount::class)->execute($child, [
                'account_category_id' => $child->account_category_id,
                'detail_type' => 'sub_account',
                'parent_id' => $parent->id,
                'header_account_ids' => [],
                'default_tax_id' => $child->default_tax_id,
                'code' => $child->code,
                'name' => $child->name,
                'description' => $child->description,
                'is_header' => false,
            ]);

            $this->fail('Updating an account under an archived parent should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('detail_type', $exception->errors());
        }
    }

    public function test_rejects_a_cross_category_account_as_an_updated_parent(): void
    {
        $child = ChartOfAccount::query()->where('is_header', false)->firstOrFail();
        $parent = ChartOfAccount::query()
            ->where('is_header', false)
            ->where('account_category_id', '!=', $child->account_category_id)
            ->firstOrFail();

        try {
            app(UpdateChartOfAccount::class)->execute($child, [
                'account_category_id' => $child->account_category_id,
                'detail_type' => 'sub_account',
                'parent_id' => $parent->id,
                'header_account_ids' => [],
                'default_tax_id' => $child->default_tax_id,
                'code' => $child->code,
                'name' => $child->name,
                'description' => $child->description,
                'is_header' => false,
            ]);

            $this->fail('Updating an account under a cross-category parent should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('parent_id', $exception->errors());
            $this->assertSame(
                'Akun yang dipilih harus berada dalam kategori yang sama.',
                $exception->errors()['parent_id'][0],
            );
        }
    }

    public function test_rejects_a_header_account_cycle_when_updating(): void
    {
        $ancestor = ChartOfAccount::query()->where('is_header', false)->firstOrFail();

        $descendant = app(CreateChartOfAccount::class)->execute([
            'account_category_id' => $ancestor->account_category_id,
            'detail_type' => 'sub_account',
            'parent_id' => $ancestor->id,
            'header_account_ids' => [],
            'default_tax_id' => null,
            'code' => $ancestor->code.'01',
            'name' => 'Header cycle descendant',
            'description' => null,
            'is_header' => false,
        ]);

        try {
            app(UpdateChartOfAccount::class)->execute($descendant, [
                'account_category_id' => $descendant->account_category_id,
                'detail_type' => 'header',
                'parent_id' => null,
                'header_account_ids' => [$ancestor->id],
                'default_tax_id' => $descendant->default_tax_id,
                'code' => $descendant->code,
                'name' => $descendant->name,
                'description' => $descendant->description,
                'is_header' => true,
            ]);

            $this->fail('Updating a header with its ancestor as a child should fail validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('header_account_ids', $exception->errors());
            $this->assertSame(
                'Akun header tidak boleh menjadi turunan dari dirinya sendiri.',
                $exception->errors()['header_account_ids'][0],
            );
        }
    }

    private function dropSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
    }
}
