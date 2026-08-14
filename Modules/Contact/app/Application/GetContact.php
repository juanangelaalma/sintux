<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class GetContact
{
    public function __construct(
        private readonly ContactPresenter $presenter,
    ) {}

    /**
     * Get a single contact with its addresses, scoped to the branch list.
     *
     * @param  list<int>  $branchIds
     * @return array<string, mixed>
     */
    public function execute(int $id, array $branchIds): array
    {
        $contact = Contact::with(['billingAddress', 'shippingAddress'])->findOrFail($id);

        abort_unless(in_array($contact->branch_id, $branchIds, true), 403);

        return $this->presenter->serializeForDetail($contact);
    }
}
