<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class GetContacts
{
    public function __construct(
        private readonly ContactPresenter $presenter,
    ) {}

    /**
     * Get list of contacts filtered by type and branch scope.
     *
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    public function execute(string $type, array $branchIds): array
    {
        return Contact::query()
            ->where('type', $type)
            ->whereIn('branch_id', $branchIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Contact $contact) => $this->presenter->serializeForList($contact))
            ->all();
    }
}
