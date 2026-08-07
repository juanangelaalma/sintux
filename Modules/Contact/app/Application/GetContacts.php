<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class GetContacts
{
    public function __construct(
        private readonly ContactPresenter $presenter,
    ) {}

    /**
     * Get list of contacts filtered by type.
     *
     * @return list<array<string, mixed>>
     */
    public function execute(string $type): array
    {
        return Contact::with(['billingAddress', 'shippingAddress'])
            ->where('type', $type)
            ->orderBy('name')
            ->get()
            ->map(fn (Contact $contact) => $this->presenter->serialize($contact))
            ->all();
    }
}
