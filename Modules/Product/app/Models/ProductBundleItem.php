<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bundle_product_id
 * @property int $item_product_id
 * @property float $quantity
 * @property-read Product|null $bundleProduct
 * @property-read Product|null $itemProduct
 */
class ProductBundleItem extends Model
{
    protected $fillable = [
        'bundle_product_id',
        'item_product_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'bundle_product_id' => 'integer',
            'item_product_id' => 'integer',
            'quantity' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function bundleProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'bundle_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function itemProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'item_product_id');
    }
}
