<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    /**
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'schema_name',
            'is_active',
        ];
    }

    public function getDatabaseName(): string
    {
        return $this->schema_name ?? ('company_'.$this->id);
    }
}
