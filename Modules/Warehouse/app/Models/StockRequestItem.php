<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\ProductVariant;

class StockRequestItem extends Model
{
    protected $fillable = [
        'stock_request_id',
        'product_variant_id',
        'qty_requested',
        'qty_approved',
    ];

    protected function casts(): array
    {
        return [
            'stock_request_id' => 'integer',
            'product_variant_id' => 'integer',
            'qty_requested' => 'decimal:4',
            'qty_approved' => 'decimal:4',
        ];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
