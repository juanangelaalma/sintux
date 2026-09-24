<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePaymentWithholding extends Model
{
    protected $fillable = [
        'purchase_payment_id',
        'account_id',
        'type',
        'value',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'amount' => 'decimal:4',
        ];
    }

    public function purchasePayment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class, 'purchase_payment_id');
    }
}
