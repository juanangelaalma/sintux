<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Product\Models\ProductVariant;

class StockTransferDiscrepancy extends Model
{
    protected $fillable = [
        'stock_transfer_id',
        'stock_transfer_item_id',
        'product_variant_id',
        'shipped_qty',
        'received_qty',
        'difference_qty',
        'reason',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'stock_transfer_id' => 'integer',
            'stock_transfer_item_id' => 'integer',
            'product_variant_id' => 'integer',
            'shipped_qty' => 'decimal:4',
            'received_qty' => 'decimal:4',
            'difference_qty' => 'decimal:4',
            'resolved_at' => 'datetime',
        ];
    }

    public function stockTransfer(): BelongsTo
    {
        return $this->belongsTo(StockTransfer::class, 'stock_transfer_id');
    }

    public function stockTransferItem(): BelongsTo
    {
        return $this->belongsTo(StockTransferItem::class, 'stock_transfer_item_id');
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
