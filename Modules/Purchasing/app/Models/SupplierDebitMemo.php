<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int|null $purchase_return_id
 * @property int|null $purchase_invoice_id
 * @property string $status
 * @property float $total
 * @property float $remaining
 */
class SupplierDebitMemo extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'purchase_return_id',
        'purchase_invoice_id',
        'status',
        'total',
        'remaining',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
            'remaining' => 'decimal:4',
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
     * @return BelongsTo<PurchaseInvoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }
}
