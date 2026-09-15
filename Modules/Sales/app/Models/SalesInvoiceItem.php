<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sales_invoice_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property int $qty
 * @property float $unit_price
 * @property string|null $discount_type
 * @property float $discount_value
 * @property float $discount_amount
 * @property float $line_gross
 * @property float $line_net
 * @property int|null $tax_id
 * @property float $tax_rate
 * @property float $tax_amount
 * @property float $line_total
 */
class SalesInvoiceItem extends Model
{
    protected $fillable = [
        'sales_invoice_id',
        'product_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'qty',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'line_gross',
        'line_net',
        'tax_id',
        'tax_rate',
        'tax_amount',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'unit_price' => 'decimal:4',
            'discount_value' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'line_gross' => 'decimal:4',
            'line_net' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<SalesInvoice, $this>
     */
    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }
}
