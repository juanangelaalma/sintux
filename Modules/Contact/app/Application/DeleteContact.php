<?php

namespace Modules\Contact\Application;

use Illuminate\Support\Facades\DB;
use Modules\Contact\Models\Contact;

class DeleteContact
{
    /**
     * Delete a contact by ID, scoped to the branch list.
     *
     * @param  list<int>  $branchIds
     */
    public function execute(int $contactId, array $branchIds, string $expectedType = ''): void
    {
        DB::transaction(function () use ($contactId, $branchIds, $expectedType) {
            /** @var Contact $contact */
            $contact = Contact::lockForUpdate()->findOrFail($contactId);

            if (! in_array($contact->branch_id, $branchIds, true)) {
                abort(404);
            }

            if ($expectedType !== '' && $contact->type !== $expectedType) {
                abort(404);
            }

            $contact->delete();
        });
    }
}
