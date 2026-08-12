<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StockRequest extends Model
{
    protected $fillable = [
        'requesting_warehouse_id',
        'destination_warehouse_id',
        'requested_by',
        'status',
        'note',
        'requested_at',
    ];

    protected function casts(): array
    {
        return [
            'requesting_warehouse_id' => 'integer',
            'destination_warehouse_id' => 'integer',
            'requested_by' => 'integer',
            'requested_at' => 'datetime',
        ];
    }

    public function requestingWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'requesting_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockRequestItem::class, 'stock_request_id');
    }

    public function transfer(): HasOne
    {
        return $this->hasOne(StockTransfer::class, 'stock_request_id');
    }
}
