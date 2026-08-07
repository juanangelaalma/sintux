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
        'registered_at',
        'tier_relation',
        'identity_type',
        'identity_number',
        'company_name',
        'email',
        'mobile_phone',
        'telephone',
        'fax',
        'npwp',
        'notes',
        'bank_name',
        'bank_branch',
        'bank_account_name',
        'bank_account_number',
        'is_active',
        'shipping_same_as_billing',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'date:Y-m-d',
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
