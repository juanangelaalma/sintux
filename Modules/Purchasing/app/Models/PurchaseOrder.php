<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int|null $source_request_id
 * @property int|null $source_quote_id
 * @property string $status
 * @property string $order_date
 * @property string|null $expected_date
 * @property string|null $note
 * @property string $currency_code
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PurchaseOrderItem> $items
 */
class PurchaseOrder extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'source_request_id',
        'source_quote_id',
        'status',
        'order_date',
        'expected_date',
        'note',
        'currency_code',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'order_date' => 'date',
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
}
