<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int $purchase_order_id
 * @property int $warehouse_id
 * @property string $status
 * @property string $receipt_date
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, GoodsReceiptItem> $items
 */
class GoodsReceipt extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'purchase_order_id',
        'warehouse_id',
        'status',
        'receipt_date',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
        ];
    }

    /**
     * @return HasMany<GoodsReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
