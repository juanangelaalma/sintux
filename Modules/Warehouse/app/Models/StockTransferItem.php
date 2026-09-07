<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Product\Models\ProductVariant;

class StockTransferItem extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'product_variant_id',
        'qty',
        'qty_shipped',
        'qty_received',
    ];

    protected function casts(): array
    {
        return [
            'stock_transfer_id' => 'integer',
            'product_variant_id' => 'integer',
            'qty' => 'decimal:4',
            'qty_shipped' => 'decimal:4',
            'qty_received' => 'decimal:4',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function layers(): HasMany
    {
        return $this->hasMany(StockTransferItemLayer::class, 'stock_transfer_item_id');
    }

    public function discrepancies(): HasMany
    {
        return $this->hasMany(StockTransferDiscrepancy::class, 'stock_transfer_item_id');
    }
}
