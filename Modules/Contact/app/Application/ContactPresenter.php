<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;
use Modules\Contact\Models\ContactAddress;

class ContactPresenter
{
    /**
     * Serialize a contact with its billing/shipping addresses for Inertia.
     *
     * @return array<string, mixed>
     */
    public function serialize(Contact $contact): array
    {
        $sameAsBilling = (bool) $contact->shipping_same_as_billing;

        return [
            'id' => $contact->id,
            'type' => $contact->type,
            'name' => $contact->name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'notes' => $contact->notes,
            'is_active' => $contact->is_active,
            'shipping_same_as_billing' => $sameAsBilling,
            'billing_address' => $contact->billingAddress
                ? $this->serializeAddress($contact->billingAddress)
                : null,
            'shipping_address' => $sameAsBilling
                ? null
                : ($contact->shippingAddress ? $this->serializeAddress($contact->shippingAddress) : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAddress(ContactAddress $address): array
    {
        return [
            'id' => $address->id,
            'type' => $address->type,
            'detail' => $address->detail,
            'rt' => $address->rt,
            'rw' => $address->rw,
            'kelurahan' => $address->kelurahan,
            'kecamatan' => $address->kecamatan,
            'kabupaten' => $address->kabupaten,
            'provinsi' => $address->provinsi,
            'latitude' => $address->latitude,
            'longitude' => $address->longitude,
        ];
    }
}
