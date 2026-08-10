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
     * @param  list<int>  $branchIds
     */
    public function execute(int $contactId, array $data, array $branchIds): Contact
    {
        $contact = Contact::findOrFail($contactId);

        abort_unless(in_array($contact->branch_id, $branchIds, true), 403);

        if (array_key_exists('branch_id', $data)) {
            abort_unless(in_array((int) $data['branch_id'], $branchIds, true), 403);
        }

        if ($contact->type !== 'customer') {
            $data['tier_relation'] = null;
        }

        $contact->update($data);

        $this->syncContactAddresses->execute($contact, $data);

        return $contact;
    }
}
