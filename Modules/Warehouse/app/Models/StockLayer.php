<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLayer extends Model
{
    protected $fillable = [
        'product_variant_id',
        'warehouse_id',
        'qty_remaining',
        'unit_cost',
        'received_at',
        'source_type',
        'source_id',
        'root_source_type',
        'root_source_id',
        'parent_layer_id',
    ];

    protected function casts(): array
    {
        return [
            'product_variant_id' => 'integer',
            'warehouse_id' => 'integer',
            'qty_remaining' => 'integer',
            'unit_cost' => 'decimal:4',
            'received_at' => 'datetime',
            'source_id' => 'integer',
            'root_source_id' => 'integer',
            'parent_layer_id' => 'integer',
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

    /**
     * @return BelongsTo<StockLayer, $this>
     */
    public function parentLayer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class, 'parent_layer_id');
    }
}
