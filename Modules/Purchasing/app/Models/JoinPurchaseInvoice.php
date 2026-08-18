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
 * @property string $status
 * @property string $join_date
 * @property string|null $note
 * @property string $currency_code
 * @property float $total_amount
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, JoinPurchaseInvoiceItem> $items
 */
class JoinPurchaseInvoice extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'status',
        'join_date',
        'note',
        'currency_code',
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:4',
            'join_date' => 'date',
        ];
    }

    /**
     * @return HasMany<JoinPurchaseInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(JoinPurchaseInvoiceItem::class);
    }
}
