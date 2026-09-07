<?php

namespace Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Company\Models\Branch;
use Modules\Warehouse\Models\Warehouse;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $destination_branch_id
 * @property int|null $destination_warehouse_id
 * @property int $product_variant_id
 * @property string $product_name
 * @property string $sku
 * @property string|null $uom_name
 * @property string|null $description
 * @property float $qty_ordered
 * @property float $qty_received
 * @property float $unit_price
 * @property int|null $tax_id
 * @property float $tax_rate
 * @property float $line_total
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'destination_branch_id',
        'destination_warehouse_id',
        'destination_expected_date',
        'product_variant_id',
        'product_name',
        'sku',
        'uom_name',
        'description',
        'qty_ordered',
        'qty_received',
        'unit_price',
        'tax_id',
        'tax_rate',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'destination_expected_date' => 'date',
            'qty_ordered' => 'decimal:4',
            'qty_received' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function destinationBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'destination_branch_id');
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }
}
