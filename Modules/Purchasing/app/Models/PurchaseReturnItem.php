<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_return_id
 * @property int $purchase_invoice_item_id
 * @property int $product_variant_id
 * @property int|null $stock_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property float $qty
 * @property float $unit_price
 * @property int|null $tax_id
 * @property float $tax_rate
 * @property array|null $tax_breakdown
 * @property float $line_total
 */
class PurchaseReturnItem extends Model
{
    protected $fillable = [
        'purchase_return_id',
        'purchase_invoice_item_id',
        'product_variant_id',
        'stock_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'qty',
        'unit_price',
        'tax_id',
        'tax_rate',
        'tax_breakdown',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_breakdown' => 'array',
            'line_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseReturn, $this>
     */
    public function purchaseReturn(): BelongsTo
    {
        return $this->belongsTo(PurchaseReturn::class);
    }

    /**
     * @return BelongsTo<PurchaseInvoiceItem, $this>
     */
    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoiceItem::class, 'purchase_invoice_item_id');
    }
}
