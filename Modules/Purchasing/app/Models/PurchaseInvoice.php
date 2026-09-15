<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $goods_receipt_id
 * @property PurchaseInvoiceStatus $status
 * @property string $invoice_date
 * @property string|null $due_date
 * @property string|null $note
 * @property string $currency_code
 * @property bool $is_tax_inclusive
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PurchaseInvoiceItem> $items
 */
class PurchaseInvoice extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'purchase_order_id',
        'goods_receipt_id',
        'status',
        'invoice_date',
        'due_date',
        'note',
        'currency_code',
        'is_tax_inclusive',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseInvoiceStatus::class,
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * @return HasMany<PurchaseInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class);
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }
}
