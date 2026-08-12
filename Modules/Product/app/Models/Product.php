<?php

namespace Modules\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function bundleItems(): HasMany
    {
        return $this->hasMany(ProductBundleItem::class, 'bundle_product_id');
    }
}
