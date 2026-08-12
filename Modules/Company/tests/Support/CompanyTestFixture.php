<?php

namespace Modules\Company\Tests\Support;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class CompanyTestFixture
{
    public static function resetMemberships(): void
    {
        DB::table('company_users')->delete();
    }

    /**
     * @param  array{name: string, code: string, is_headquarters?: bool}  $attributes
     */
    public static function branch(Tenant $tenant, array $attributes): int
    {
        tenancy()->initialize($tenant);

        try {
            $existingId = DB::table('branches')
                ->where('code', $attributes['code'])
                ->value('id');

            if ($existingId) {
                return (int) $existingId;
            }

            return (int) DB::table('branches')->insertGetId([
                ...$attributes,
                'is_active' => true,
            ]);
        } finally {
            tenancy()->end();
        }
    }
}
