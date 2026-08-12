<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();

        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        $this->dropLeftoverSchemas();

        parent::tearDown();
    }

    public function test_can_upload_valid_jpg_and_png_images(): void
    {
        Storage::fake('public');

        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Upload JPG
        $jpgFile = UploadedFile::fake()->image('product.jpg', 600, 600);
        $responseJpg = $this->actingAs($user)->postJson(route('product.products.upload-image'), [
            'image' => $jpgFile,
        ]);

        $responseJpg->assertStatus(200)
            ->assertJsonStructure(['success', 'path', 'url']);

        Storage::disk('public')->assertExists($responseJpg->json('path'));

        // Upload PNG
        $pngFile = UploadedFile::fake()->image('product.png', 400, 400);
        $responsePng = $this->actingAs($user)->postJson(route('product.products.upload-image'), [
            'image' => $pngFile,
        ]);

        $responsePng->assertStatus(200)
            ->assertJsonStructure(['success', 'path', 'url']);

        Storage::disk('public')->assertExists($responsePng->json('path'));
    }

    public function test_rejects_unallowed_file_types(): void
    {
        Storage::fake('public');

        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Try PDF
        $pdfFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');
        $responsePdf = $this->actingAs($user)->postJson(route('product.products.upload-image'), [
            'image' => $pdfFile,
        ]);

        $responsePdf->assertStatus(422)
            ->assertJsonValidationErrors(['image']);

        // Try PHP script fake
        $phpFile = UploadedFile::fake()->create('shell.php', 10, 'text/x-php');
        $responsePhp = $this->actingAs($user)->postJson(route('product.products.upload-image'), [
            'image' => $phpFile,
        ]);

        $responsePhp->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    public function test_rejects_over_5mb_images(): void
    {
        Storage::fake('public');

        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Fake image 6MB (6144 KB)
        $largeFile = UploadedFile::fake()->image('huge.jpg')->size(6144);
        $response = $this->actingAs($user)->postJson(route('product.products.upload-image'), [
            'image' => $largeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['image']);
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('img_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Image Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        [$branchId, $member] = $this->provision($tenant, 'member_'.$id.'@acme.test');

        return [$tenant->id, $branchId, $member];
    }

    private function provision(Tenant $tenant, string $email): array
    {
        tenancy()->initialize($tenant);
        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $branchId = $existingHq ? (int) $existingHq : DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
        tenancy()->end();

        $user = User::factory()->create(['email' => $email, 'role' => 'user']);

        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'member',
            'is_default' => true,
        ]);

        return [$branchId, $user];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            DB::statement('DROP SCHEMA IF EXISTS "'.$schemaName.'" CASCADE');
        } catch (\Exception $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'"
            );
            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Exception $e) {
        }
    }
}
