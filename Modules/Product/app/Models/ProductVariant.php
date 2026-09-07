<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $branch_id
 * @property int $product_id
 * @property string $sku
 * @property string $variant_name
 * @property array<string, mixed>|null $attributes
 * @property bool $is_active
 * @property-read Product|null $product
 */
class ProductVariant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'product_id',
        'sku',
        'variant_name',
        'attributes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
