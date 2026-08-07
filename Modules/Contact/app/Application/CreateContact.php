<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class CreateContact
{
    public function __construct(
        private readonly SyncContactAddresses $syncContactAddresses,
    ) {}

    /**
     * Create a new contact for the active tenant.
     *
     * @param  array{name: string, email?: string|null, phone?: string|null, notes?: string|null, is_active?: bool, shipping_same_as_billing?: bool, billing_address?: array|null, shipping_address?: array|null}  $data
     */
    public function execute(string $type, array $data): Contact
    {
        $contact = Contact::create([
            'type' => $type,
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'shipping_same_as_billing' => $data['shipping_same_as_billing'] ?? true,
        ]);

        $this->syncContactAddresses->execute($contact, $data);

        return $contact;
    }
}
