<?php

namespace Modules\Contact\Application;

use Illuminate\Support\Facades\DB;
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
    public function execute(int $contactId, array $data, array $branchIds, string $expectedType = ''): Contact
    {
        return DB::transaction(function () use ($contactId, $data, $branchIds, $expectedType) {
            /** @var Contact $contact */
            $contact = Contact::lockForUpdate()->findOrFail($contactId);

            if (! in_array($contact->branch_id, $branchIds, true)) {
                abort(404);
            }

            if ($expectedType !== '' && $contact->type !== $expectedType) {
                abort(404);
            }

            if (array_key_exists('branch_id', $data)) {
                if (! in_array((int) $data['branch_id'], $branchIds, true)) {
                    abort(404);
                }
            }

            if ($contact->type !== 'customer') {
                $data['tier_relation'] = null;
            }

            $contact->update($data);

            $this->syncContactAddresses->execute($contact, $data);

            return $contact;
        });
    }
}
