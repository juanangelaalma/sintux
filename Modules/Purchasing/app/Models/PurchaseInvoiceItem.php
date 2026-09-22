<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_invoice_id
 * @property int|null $purchase_order_item_id
 * @property int|null $goods_receipt_item_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property string|null $color_raw
 * @property string|null $color
 * @property float $qty
 * @property float $unit_price
 * @property int|null $tax_id
 * @property float $tax_rate
 * @property array|null $tax_breakdown
 * @property float $line_total
 */
class PurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'purchase_invoice_id',
        'purchase_order_item_id',
        'goods_receipt_item_id',
        'product_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'color_raw',
        'color',
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
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
