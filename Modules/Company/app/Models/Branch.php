<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'name',
        'code',
        'address',
        'phone',
        'is_active',
        'is_headquarters',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_headquarters' => 'boolean',
        ];
    }
}
