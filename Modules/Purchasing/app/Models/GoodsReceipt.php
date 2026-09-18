<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Purchasing\Enums\GoodsReceiptStatus;

/**
 * Dokumen penerimaan tunggal (GRN): fetch DO supplier → verifikasi fisik
 * cabang → submit → approval HO (posting stok + transfer) → faktur.
 *
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $supplier_id
 * @property int $purchase_order_id
 * @property int $warehouse_id
 * @property string $supplier_do_no
 * @property string|null $supplier_invoice_no
 * @property string|null $po_no
 * @property string|null $rejection_reason
 * @property GoodsReceiptStatus $status
 * @property string $receipt_date
 * @property string|null $note
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, GoodsReceiptItem> $items
 */
class GoodsReceipt extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'purchase_order_id',
        'warehouse_id',
        'supplier_do_no',
        'supplier_invoice_no',
        'po_no',
        'do_date',
        'cust_name',
        'driver',
        'nopol',
        'transaction_type',
        'status',
        'receipt_date',
        'note',
        'rejection_reason',
        'submitted_by',
        'submitted_at',
        'decided_by',
        'decided_at',
        'transferred_at',
        'transfer_error',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'status' => GoodsReceiptStatus::class,
            'receipt_date' => 'date',
            'do_date' => 'date',
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
            'transferred_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * @return HasMany<GoodsReceiptItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }
}
