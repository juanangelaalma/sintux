<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property string|null $supplier_email
 * @property string|null $supplier_reference
 * @property string|null $billing_address
 * @property int|null $warehouse_id
 * @property int|null $source_request_id
 * @property int|null $source_quote_id
 * @property string $branch_mode
 * @property string $status
 * @property string|null $payment_term
 * @property string $order_date
 * @property string|null $due_date
 * @property string|null $expected_date
 * @property string|null $note
 * @property string $currency_code
 * @property bool $is_tax_inclusive
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PurchaseOrderItem> $items
 * @property-read Collection<int, PurchaseTag> $tags
 */
class PurchaseOrder extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'supplier_email',
        'supplier_reference',
        'billing_address',
        'warehouse_id',
        'source_request_id',
        'source_quote_id',
        'branch_mode',
        'status',
        'payment_term',
        'order_date',
        'due_date',
        'expected_date',
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
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'order_date' => 'date',
            'due_date' => 'date',
            'expected_date' => 'date',
        ];
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * @return BelongsToMany<PurchaseTag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(PurchaseTag::class);
    }
}
