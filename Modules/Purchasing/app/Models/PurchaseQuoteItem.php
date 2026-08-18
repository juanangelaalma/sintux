<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_quote_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property float $qty
 * @property float $unit_price
 * @property int|null $tax_id
 * @property float $tax_rate
 * @property float $line_total
 */
class PurchaseQuoteItem extends Model
{
    protected $fillable = [
        'purchase_quote_id',
        'product_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'qty',
        'unit_price',
        'tax_id',
        'tax_rate',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseQuote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class, 'purchase_quote_id');
    }
}
