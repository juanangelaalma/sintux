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
     * @param  array<string, mixed>  $data
     */
    public function execute(int $contactId, array $data): Contact
    {
        $contact = Contact::findOrFail($contactId);

        if ($contact->type !== 'customer') {
            $data['tier_relation'] = null;
        }

        $contact->update($data);

        $this->syncContactAddresses->execute($contact, $data);

        return $contact;
    }
}
