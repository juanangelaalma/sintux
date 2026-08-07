<?php

namespace Modules\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contact extends Model
{
    protected $fillable = [
        'type',
        'name',
        'email',
        'phone',
        'notes',
        'is_active',
        'shipping_same_as_billing',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'shipping_same_as_billing' => 'boolean',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(ContactAddress::class);
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(ContactAddress::class)->where('type', 'billing');
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(ContactAddress::class)->where('type', 'shipping');
    }
}
