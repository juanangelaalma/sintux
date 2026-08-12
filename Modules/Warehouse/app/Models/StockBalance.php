<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\ProductVariant;

class StockBalance extends Model
{
    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'qty_on_hand',
    ];

    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'warehouse_id' => 'integer',
            'qty_on_hand' => 'integer',
        ];
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
