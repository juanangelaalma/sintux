<?php

namespace Modules\Contact\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class ContactCrudTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Remove tenant schemas left behind by previous
         * failed/interrupted tests.
         */
        $this->dropLeftoverSchemas();

        /*
         * Clean central tables in FK-safe order.
         */
        $this->cleanupCentralTables();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Drop the tenant schema created by this test.
         */
        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_company_member_can_manage_contacts(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // 1. Get List (Empty)
        $this->actingAs($user)
            ->get(route('company.contacts.index', 'customers'))
            ->assertStatus(200);

        // 2. Create Customer with billing address (shipping same as billing)
        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'Acme Customer',
            'registered_at' => '2026-08-01',
            'tier_relation' => 'A',
            'email' => 'acme@customer.test',
            'mobile_phone' => '123456',
            'notes' => 'Important customer',
            'is_active' => true,
            'shipping_same_as_billing' => true,
            'billing_address' => [
                'detail' => 'Jl. Melati No. 5',
                'rt' => '001',
                'rw' => '002',
                'kelurahan' => 'Cibubur',
                'kecamatan' => 'Ciracas',
                'kabupaten' => 'Jakarta Timur',
                'provinsi' => 'DKI Jakarta',
                'latitude' => -6.3296,
                'longitude' => 106.8766,
            ],
        ])->assertRedirect(route('company.contacts.index', 'customers'));

        // Verify in Tenant DB
        tenancy()->initialize($tenantId);
        $customer = DB::table('contacts')->where('email', 'acme@customer.test')->first();
        $this->assertNotNull($customer);
        $this->assertSame('customer', $customer->type);
        $this->assertSame('Acme Customer', $customer->name);
        $this->assertSame('A', $customer->tier_relation);
        $this->assertSame('2026-08-01', $customer->registered_at);
        $this->assertSame('123456', $customer->mobile_phone);
        $this->assertTrue((bool) $customer->is_active);
        $this->assertTrue((bool) $customer->shipping_same_as_billing);

        $addresses = DB::table('contact_addresses')->where('contact_id', $customer->id)->get();
        $this->assertCount(1, $addresses);
        $this->assertSame('billing', $addresses->first()->type);
        $this->assertSame('Cibubur', $addresses->first()->kelurahan);
        $this->assertSame('Ciracas', $addresses->first()->kecamatan);
        tenancy()->end();

        // 3. Update Customer (partial update must not touch addresses)
        $this->actingAs($user)->put(route('company.contacts.update', ['type' => 'customers', 'id' => $customer->id]), [
            'name' => 'Acme Customer Updated',
            'email' => 'acme.updated@customer.test',
            'is_active' => false,
        ])->assertRedirect(route('company.contacts.index', 'customers'));

        tenancy()->initialize($tenantId);
        $customerUpdated = DB::table('contacts')->where('id', $customer->id)->first();
        $this->assertSame('Acme Customer Updated', $customerUpdated->name);
        $this->assertSame('acme.updated@customer.test', $customerUpdated->email);
        $this->assertFalse((bool) $customerUpdated->is_active);
        $this->assertDatabaseHas('contact_addresses', [
            'contact_id' => $customer->id,
            'type' => 'billing',
            'kelurahan' => 'Cibubur',
        ]);
        tenancy()->end();

        // 4. Delete Customer
        $this->actingAs($user)->delete(route('company.contacts.destroy', ['type' => 'customers', 'id' => $customer->id]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $this->assertDatabaseMissing('contacts', ['id' => $customer->id]);
        $this->assertDatabaseMissing('contact_addresses', ['contact_id' => $customer->id]);
        tenancy()->end();
    }

    public function test_separate_billing_and_shipping_addresses_are_persisted(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('company.contacts.store', 'suppliers'), [
            'branch_id' => $branchId,
            'name' => 'Billing Shop',
            'email' => 'billing@shop.test',
            'registered_at' => '2026-08-01',
            'shipping_same_as_billing' => false,
            'billing_address' => [
                'detail' => 'Jl. A No. 1',
                'rt' => '001',
                'rw' => '002',
                'kelurahan' => 'Kelurahan Billing',
                'kecamatan' => 'Kecamatan Billing',
                'kabupaten' => 'Kota Billing',
                'provinsi' => 'Provinsi Billing',
                'latitude' => -6.2,
                'longitude' => 106.8,
            ],
            'shipping_address' => [
                'detail' => 'Jl. C No. 2',
                'rt' => '003',
                'rw' => '004',
                'kelurahan' => 'Kelurahan Shipping',
                'kecamatan' => 'Kecamatan Shipping',
                'kabupaten' => 'Kota Shipping',
                'provinsi' => 'Provinsi Shipping',
                'latitude' => -6.3,
                'longitude' => 106.9,
            ],
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $supplier = DB::table('contacts')->where('email', 'billing@shop.test')->first();
        $this->assertFalse((bool) $supplier->shipping_same_as_billing);
        $this->assertSame(2, DB::table('contact_addresses')->where('contact_id', $supplier->id)->count());
        $this->assertDatabaseHas('contact_addresses', [
            'contact_id' => $supplier->id,
            'type' => 'shipping',
            'kelurahan' => 'Kelurahan Shipping',
        ]);
        tenancy()->end();

        // Toggle back to "same as billing" -> shipping row is removed, billing kept
        $this->actingAs($user)->put(route('company.contacts.update', ['type' => 'suppliers', 'id' => $supplier->id]), [
            'shipping_same_as_billing' => true,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $this->assertDatabaseMissing('contact_addresses', [
            'contact_id' => $supplier->id,
            'type' => 'shipping',
        ]);
        $this->assertDatabaseHas('contact_addresses', [
            'contact_id' => $supplier->id,
            'type' => 'billing',
        ]);
        tenancy()->end();
    }

    public function test_empty_address_payload_does_not_create_address_rows(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'No Address Customer',
            'email' => 'noaddress@customer.test',
            'registered_at' => '2026-08-01',
            'shipping_same_as_billing' => true,
            'billing_address' => [
                'detail' => '',
                'rt' => '',
                'rw' => '',
                'kelurahan' => '',
                'kecamatan' => '',
                'kabupaten' => '',
                'provinsi' => '',
                'latitude' => '',
                'longitude' => '',
            ],
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $customer = DB::table('contacts')->where('email', 'noaddress@customer.test')->first();
        $this->assertSame(0, DB::table('contact_addresses')->where('contact_id', $customer->id)->count());
        tenancy()->end();
    }

    public function test_contact_address_validation_rejects_out_of_range_coordinates(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'Bad Coord Customer',
            'registered_at' => '2026-08-01',
            'billing_address' => [
                'latitude' => 999,
                'longitude' => 999,
            ],
        ])->assertSessionHasErrors(['billing_address.latitude', 'billing_address.longitude']);
    }

    public function test_tier_relation_only_persisted_for_customers(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('company.contacts.store', 'suppliers'), [
            'branch_id' => $branchId,
            'name' => 'Tier Supplier',
            'email' => 'tier@supplier.test',
            'registered_at' => '2026-08-01',
            'tier_relation' => 'A',
        ])->assertRedirect(route('company.contacts.index', 'suppliers'));

        tenancy()->initialize($tenantId);
        $supplier = DB::table('contacts')->where('email', 'tier@supplier.test')->first();
        $this->assertNull($supplier->tier_relation);
        tenancy()->end();

        $this->actingAs($user)->put(route('company.contacts.update', ['type' => 'suppliers', 'id' => $supplier->id]), [
            'tier_relation' => 'B',
        ])->assertRedirect(route('company.contacts.index', 'suppliers'));

        tenancy()->initialize($tenantId);
        $updated = DB::table('contacts')->where('id', $supplier->id)->first();
        $this->assertNull($updated->tier_relation);
        tenancy()->end();
    }

    public function test_create_and_edit_pages_render(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)
            ->get(route('company.contacts.create', 'customers'))
            ->assertStatus(200);

        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'Edit Page Contact',
            'email' => 'editpage@customer.test',
            'registered_at' => '2026-08-01',
        ])->assertRedirect(route('company.contacts.index', 'customers'));

        tenancy()->initialize($tenantId);
        $customer = DB::table('contacts')->where('email', 'editpage@customer.test')->first();
        tenancy()->end();

        $this->actingAs($user)
            ->get(route('company.contacts.edit', ['type' => 'customers', 'id' => $customer->id]))
            ->assertStatus(200);
    }

    public function test_non_member_cannot_manage_contacts(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();
        $stranger = User::factory()->create(['email' => 'stranger@example.test', 'role' => 'user']);

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Attempting to access without membership -> 403
        $this->actingAs($stranger)
            ->get(route('company.contacts.index', 'customers'))
            ->assertStatus(403);

        $this->actingAs($stranger)
            ->get(route('company.contacts.create', 'customers'))
            ->assertStatus(403);

        $this->actingAs($stranger)
            ->get(route('company.contacts.edit', ['type' => 'customers', 'id' => 1]))
            ->assertStatus(403);
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('contact_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Contact Test Corp',
            'schema_name' => 'sch_'.$id,
            'is_active' => true,
        ]);

        $this->activeSchemaName = 'sch_'.$id;

        [$branchId, $member] = $this->provision($tenant, 'member_'.$id.'@acme.test');

        return [(string) $tenant->id, $branchId, $member];
    }

    /**
     * @return array{0: int, 1: User}
     */
    private function provision(Tenant $tenant, string $email): array
    {
        $branchId = CompanyTestFixture::branch($tenant, [
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);

        $user = app(CreateCompanyUser::class)->execute((string) $tenant->id, [
            'name' => 'Contact Test Member',
            'email' => $email,
            'password' => 'password',
            'company_role' => 'member',
            'branch_id' => $branchId,
            'scope' => 'branch',
        ]);

        return [$branchId, $user];
    }

    /**
     * Clean central/public tables while respecting foreign key dependencies.
     */
    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');

        $this->deleteIfTableExists('tenants');

        $this->deleteIfTableExists('users');
    }

    /**
     * Delete all rows only if the table exists in the current PostgreSQL schema.
     */
    private function deleteIfTableExists(string $table): void
    {
        $exists = DB::selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                AND table_name = ?
            ) AS exists',
            [$table]
        );

        if ($exists && (bool) $exists->exists) {
            DB::table($table)->delete();
        }
    }

    /**
     * Drop a tenant schema safely.
     */
    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
            /*
             * Cleanup failure should not hide the actual test failure.
             */
        }
    }

    /**
     * Drop leftover test tenant schemas.
     */
    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN (
                     'public',
                     'information_schema'
                 )
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                /*
                 * Only delete schemas generated by tests.
                 */
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
            /*
             * Ignore cleanup errors.
             */
        }
    }
}
