<?php

namespace Modules\Warehouse\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class StockTransfer extends Model
{
    protected $fillable = [
        'number',
        'stock_request_id',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'source_type',
        'source_id',
        'created_by',
        'approved_by',
        'approved_at',
        'shipped_by',
        'shipped_at',
        'received_by',
        'received_at',
    ];

    protected static function booted(): void
    {
        // Nomor dokumen otomatis TRF/YYYYMMDD/NNN untuk semua jalur
        // pembuatan (manual, approval request, job otomatis). NNN urut
        // harian; advisory lock membuat sekuens bebas race walau dua
        // transfer dibuat konkuren. updateQuietly agar tak memicu
        // event berulang.
        static::created(function (StockTransfer $transfer): void {
            if ($transfer->number !== null && trim((string) $transfer->number) !== '') {
                return;
            }

            $date = $transfer->created_at?->format('Ymd') ?? now()->format('Ymd');

            DB::statement("SELECT pg_advisory_xact_lock(hashtext('stock_transfer_number'))");

            $sequence = (int) DB::table('stock_transfers')
                ->whereDate('created_at', $transfer->created_at?->toDateString() ?? now()->toDateString())
                ->count();

            $transfer->updateQuietly([
                'number' => sprintf('TRF/%s/%03d', $date, $sequence),
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'stock_request_id' => 'integer',
            'from_warehouse_id' => 'integer',
            'to_warehouse_id' => 'integer',
            'created_by' => 'integer',
            'approved_by' => 'integer',
            'approved_at' => 'datetime',
            'shipped_by' => 'integer',
            'shipped_at' => 'datetime',
            'received_by' => 'integer',
            'received_at' => 'datetime',
        ];
    }

    public function stockRequest(): BelongsTo
    {
        return $this->belongsTo(StockRequest::class, 'stock_request_id');
    }

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function shippedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shipped_by');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class, 'stock_transfer_id');
    }
}
