<?php

namespace Modules\Warehouse\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransferItemLayer extends Model
{
    protected $fillable = [
        'stock_transfer_item_id',
        'stock_layer_id',
        'qty_taken',
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'stock_transfer_item_id' => 'integer',
            'stock_layer_id' => 'integer',
            'qty_taken' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function stockTransferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    public function stockLayer(): BelongsTo
    {
        return $this->belongsTo(StockLayer::class, 'stock_layer_id');
    }
}