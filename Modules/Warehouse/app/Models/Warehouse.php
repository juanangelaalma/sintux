<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Company\Models\Branch;

/**
 * @property int $id
 * @property int $branch_id
 * @property string $code
 * @property string $name
 * @property string $warehouse_type
 * @property string|null $address
 * @property bool $is_active
 */
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

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return HasMany<StockBalance, $this>
     */
    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class);
    }
}
