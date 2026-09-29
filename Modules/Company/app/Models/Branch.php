<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'currency_code',
        'address',
        'phone',
        'is_active',
        'is_headquarters',
        'supplier_customer_name',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_headquarters' => 'boolean',
        ];
    }
}
