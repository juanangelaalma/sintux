<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\ProductVariant;

class StockReservation extends Model
{
    protected $fillable = [
        'sales_invoice_id',
        'warehouse_id',
        'product_variant_id',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'sales_invoice_id' => 'integer',
            'warehouse_id' => 'integer',
            'product_variant_id' => 'integer',
            'qty' => 'integer',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
