<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasePayment extends Model
{
    protected $fillable = [
        'number',
        'branch_id',
        'supplier_id',
        'payment_method_id',
        'mode',
        'payment_date',
        'due_date',
        'currency_code',
        'cash_account_id',
        'gross_amount',
        'withholding_amount',
        'deposit_applied',
        'memo_applied',
        'cash_out',
        'deposit_total',
        'deposit_remaining',
        'status',
        'failure_reason',
        'memo',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'due_date' => 'date',
            'gross_amount' => 'decimal:4',
            'withholding_amount' => 'decimal:4',
            'deposit_applied' => 'decimal:4',
            'memo_applied' => 'decimal:4',
            'cash_out' => 'decimal:4',
            'deposit_total' => 'decimal:4',
            'deposit_remaining' => 'decimal:4',
        ];
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PurchasePaymentAllocation::class, 'purchase_payment_id');
    }

    public function withholdings(): HasMany
    {
        return $this->hasMany(PurchasePaymentWithholding::class, 'purchase_payment_id');
    }

    public function depositUses(): HasMany
    {
        return $this->hasMany(PurchasePaymentDepositApply::class, 'purchase_payment_id');
    }

    public function memoUses(): HasMany
    {
        return $this->hasMany(PurchasePaymentMemoApply::class, 'purchase_payment_id');
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }
}
