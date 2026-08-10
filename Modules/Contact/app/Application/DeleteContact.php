<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class DeleteContact
{
    /**
     * Delete a contact by ID, scoped to the branch list.
     *
     * @param  list<int>  $branchIds
     */
    public function execute(int $contactId, array $branchIds): void
    {
        $contact = Contact::findOrFail($contactId);

        abort_unless(in_array($contact->branch_id, $branchIds, true), 403);

        $contact->delete();
    }
}
