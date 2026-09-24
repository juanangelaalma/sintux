<?php

namespace Modules\Payment\Models;

use Illuminate\Database\Eloquent\Model;

class PurchasePaymentMemoApply extends Model
{
    protected $table = 'purchase_payment_memo_applies';

    protected $fillable = [
        'purchase_payment_id',
        'supplier_debit_memo_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
        ];
    }
}
