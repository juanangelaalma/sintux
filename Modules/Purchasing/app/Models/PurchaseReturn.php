<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int $purchase_invoice_id
 * @property int $warehouse_id
 * @property int|null $return_transfer_id
 * @property string $status
 * @property Carbon $return_date
 * @property string|null $message
 * @property string|null $memo
 * @property string $currency_code
 * @property bool $is_tax_inclusive
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 */
class PurchaseReturn extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'purchase_invoice_id',
        'warehouse_id',
        'return_transfer_id',
        'status',
        'return_date',
        'message',
        'memo',
        'currency_code',
        'is_tax_inclusive',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'return_date' => 'date',
        ];
    }

    /**
     * @return HasMany<PurchaseReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * @return HasMany<SupplierDebitMemo, $this>
     */
    public function debitMemos(): HasMany
    {
        return $this->hasMany(SupplierDebitMemo::class);
    }

    /**
     * @return HasMany<PurchaseReturnAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(PurchaseReturnAttachment::class);
    }

    /**
     * @return BelongsToMany<PurchaseTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseTag::class, 'purchase_return_purchase_tag');
    }
}
