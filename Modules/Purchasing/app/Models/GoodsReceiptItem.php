<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu (bundle barcode supplier, warna). Kolom level-bundle
 * (verifikasi scan, konfirmasi fisik) didenormalisasi per baris warna.
 *
 * @property int $id
 * @property int $goods_receipt_id
 * @property int|null $purchase_order_item_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property string|null $color
 * @property float $qty_received
 * @property float $qty_invoiced
 * @property float $unit_price_supplier
 * @property string $verification_status not_verified|verified
 */
class GoodsReceiptItem extends Model
{
    public const VERIFY_NOT_VERIFIED = 'not_verified';

    public const VERIFY_VERIFIED = 'verified';

    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'product_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'supplier_barcode',
        'size',
        'color_raw',
        'color',
        'qty_do',
        'qty_received',
        'qty_confirmed',
        'verification_status',
        'scanned_barcode',
        'scanned_at',
        'unit_price_supplier',
    ];

    protected function casts(): array
    {
        return [
            'qty_do' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'qty_invoiced' => 'decimal:4',
            'qty_confirmed' => 'boolean',
            'unit_price_supplier' => 'decimal:4',
            'scanned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<GoodsReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /**
     * @return BelongsTo<PurchaseOrderItem, $this>
     */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function isVerified(): bool
    {
        return $this->verification_status === self::VERIFY_VERIFIED;
    }

    public function isMapped(): bool
    {
        return $this->product_variant_id !== null;
    }
}
