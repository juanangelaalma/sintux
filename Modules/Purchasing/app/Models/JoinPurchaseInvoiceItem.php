<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $join_purchase_invoice_id
 * @property int $purchase_invoice_id
 * @property int $supplier_id
 * @property string $invoice_number
 * @property string $supplier_name
 * @property float $invoice_total
 */
class JoinPurchaseInvoiceItem extends Model
{
    protected $fillable = [
        'join_purchase_invoice_id',
        'purchase_invoice_id',
        'supplier_id',
        'invoice_number',
        'supplier_name',
        'invoice_total',
    ];

    protected function casts(): array
    {
        return [
            'invoice_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<JoinPurchaseInvoice, $this>
     */
    public function join(): BelongsTo
    {
        return $this->belongsTo(JoinPurchaseInvoice::class, 'join_purchase_invoice_id');
    }

    /**
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function purchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
