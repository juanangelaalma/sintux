<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class UpdateContact
{
    public function __construct(
        private readonly SyncContactAddresses $syncContactAddresses,
    ) {}

    /**
     * Update a contact's details and sync its addresses.
     *
     * @param  array{name?: string, email?: string|null, phone?: string|null, notes?: string|null, is_active?: bool, shipping_same_as_billing?: bool, billing_address?: array|null, shipping_address?: array|null}  $data
     */
    public function execute(int $contactId, array $data): Contact
    {
        $contact = Contact::findOrFail($contactId);

        $contact->update($data);

        $this->syncContactAddresses->execute($contact, $data);

        return $contact;
    }
}
