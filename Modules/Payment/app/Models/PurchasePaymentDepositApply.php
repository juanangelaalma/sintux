<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasePaymentDepositApply extends Model
{
    protected $table = 'purchase_payment_deposit_applies';

    protected $fillable = [
        'purchase_payment_id',
        'source_payment_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(PurchasePayment::class, 'source_payment_id');
    }
}
