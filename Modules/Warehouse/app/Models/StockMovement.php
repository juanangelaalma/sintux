<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    protected $table = 'stock_movements';

    protected $fillable = [
        'warehouse_id',
        'product_variant_id',
        'movement_type',
        'qty',
        'unit_cost',
        'stock_layer_id',
        'reference_type',
        'reference_id',
    ];

    protected $casts = [
        'warehouse_id' => 'integer',
        'product_variant_id' => 'integer',
        'qty' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'stock_layer_id' => 'integer',
        'reference_id' => 'integer',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function stockLayer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
