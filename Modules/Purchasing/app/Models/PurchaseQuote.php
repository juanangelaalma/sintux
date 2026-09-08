<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Purchasing\Enums\PurchaseQuoteStatus;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int|null $source_request_id
 * @property PurchaseQuoteStatus $status
 * @property string $quote_date
 * @property string|null $valid_until
 * @property string|null $note
 * @property string $currency_code
 * @property float $subtotal
 * @property float $tax_amount
 * @property float $total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PurchaseQuoteItem> $items
 */
class PurchaseQuote extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'source_request_id',
        'status',
        'quote_date',
        'valid_until',
        'note',
        'currency_code',
        'subtotal',
        'tax_amount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseQuoteStatus::class,
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'quote_date' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * @return HasMany<PurchaseQuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseQuoteItem::class);
    }
}
