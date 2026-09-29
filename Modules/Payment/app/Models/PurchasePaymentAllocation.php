<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePaymentAllocation extends Model
{
    protected $fillable = [
        'purchase_payment_id',
        'purchase_invoice_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function purchasePayment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class, 'purchase_payment_id');
    }
}
