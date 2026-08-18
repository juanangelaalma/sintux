<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Models\Branch;

class Warehouse extends Model
{
    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'warehouse_type',
        'address',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'branch_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }
}
