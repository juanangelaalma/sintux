<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $barcode
 * @property int $category_id
 * @property int $uom_id
 * @property string|null $description
 * @property string|null $image_path
 * @property string $product_type
 * @property bool $is_purchased
 * @property float $purchase_price
 * @property int|null $purchase_account_id
 * @property int|null $purchase_tax_id
 * @property bool $is_sold
 * @property float $selling_price
 * @property int|null $sales_account_id
 * @property int|null $sales_tax_id
 * @property bool $is_inventory_tracked
 * @property float $min_stock
 * @property int|null $inventory_account_id
 * @property bool $is_active
 * @property Carbon|null $deleted_at
 * @property-read ProductCategory|null $category
 * @property-read Uom|null $uom
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductBundleItem> $bundle_items
 */
class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'barcode',
        'category_id',
        'uom_id',
        'description',
        'image_path',
        'product_type',
        'is_purchased',
        'purchase_price',
        'purchase_account_id',
        'purchase_tax_id',
        'is_sold',
        'selling_price',
        'sales_account_id',
        'sales_tax_id',
        'is_inventory_tracked',
        'min_stock',
        'inventory_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_purchased' => 'boolean',
            'purchase_price' => 'decimal:4',
            'is_sold' => 'boolean',
            'selling_price' => 'decimal:4',
            'is_inventory_tracked' => 'boolean',
            'min_stock' => 'decimal:4',
            'is_active' => 'boolean',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<Uom, $this>
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * @return HasMany<ProductBundleItem, $this>
     */
    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id');
    }
}
