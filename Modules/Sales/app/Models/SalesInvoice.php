<?php

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Sales\Enums\SalesInvoiceStatus;

/**
 * @property int $id
 * @property string $number
 * @property int $branch_id
 * @property int $customer_id
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string $transaction_type
 * @property int $warehouse_id
 * @property string $warehouse_code
 * @property string $warehouse_name
 * @property int $salesperson_id
 * @property string $salesperson_name
 * @property string|null $payment_term
 * @property string $invoice_date
 * @property string|null $due_date
 * @property SalesInvoiceStatus $status
 * @property string $currency_code
 * @property bool $is_tax_inclusive
 * @property float $subtotal
 * @property float $line_discount_total
 * @property string|null $invoice_discount_type
 * @property float $invoice_discount_value
 * @property float $invoice_discount_amount
 * @property float $tax_amount
 * @property float $total
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, SalesInvoiceItem> $items
 */
class SalesInvoice extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'customer_id',
        'customer_name',
        'customer_email',
        'transaction_type',
        'warehouse_id',
        'warehouse_code',
        'warehouse_name',
        'salesperson_id',
        'salesperson_name',
        'payment_term',
        'invoice_date',
        'due_date',
        'status',
        'currency_code',
        'is_tax_inclusive',
        'subtotal',
        'line_discount_total',
        'invoice_discount_type',
        'invoice_discount_value',
        'invoice_discount_amount',
        'tax_amount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'status' => SalesInvoiceStatus::class,
            'is_tax_inclusive' => 'boolean',
            'subtotal' => 'decimal:4',
            'line_discount_total' => 'decimal:4',
            'invoice_discount_value' => 'decimal:4',
            'invoice_discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'invoice_date' => 'date',
            'due_date' => 'date',
        ];
    }

    /**
     * @return HasMany<SalesInvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesInvoiceItem::class);
    }
}
