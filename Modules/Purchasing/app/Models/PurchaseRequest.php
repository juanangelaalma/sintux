<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Purchasing\Enums\PurchaseRequestStatus;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int|null $supplier_id
 * @property PurchaseRequestStatus $status
 * @property string $request_date
 * @property string|null $expected_date
 * @property string|null $note
 * @property string $currency_code
 * @property float|null $subtotal
 * @property float|null $tax_amount
 * @property float|null $total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, PurchaseRequestItem> $items
 */
class PurchaseRequest extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'status',
        'request_date',
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
            'status' => PurchaseRequestStatus::class,
            'subtotal' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'request_date' => 'date',
            'expected_date' => 'date',
        ];
    }

    /**
     * @return HasMany<PurchaseRequestItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}
