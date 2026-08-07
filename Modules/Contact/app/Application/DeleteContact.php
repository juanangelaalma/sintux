<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class DeleteContact
{
    /**
     * Delete a contact by ID.
     */
    public function execute(int $contactId): void
    {
        Contact::findOrFail($contactId)->delete();
    }
}
