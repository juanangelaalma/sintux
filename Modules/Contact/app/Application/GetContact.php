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
    public function execute(int $id, array $branchIds, string $expectedType = ''): array
    {
        $contact = Contact::findOrFail($id);

        if (! in_array($contact->branch_id, $branchIds, true)) {
            abort(404);
        }

        if ($expectedType !== '' && $contact->type !== $expectedType) {
            abort(404);
        }

        return $this->presenter->serializeForDetail($contact);
    }
}
