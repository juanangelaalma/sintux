<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_request_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property float $qty_requested
 * @property float $qty_ordered
 * @property float|null $unit_price
 * @property int|null $tax_id
 * @property float|null $tax_rate
 * @property float|null $line_total
 */
class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id',
        'product_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'qty_requested',
        'qty_ordered',
        'unit_price',
        'tax_id',
        'tax_rate',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty_requested' => 'decimal:4',
            'qty_ordered' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }
}
