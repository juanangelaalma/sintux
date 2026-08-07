<?php

namespace Modules\Contact\Application;

use Modules\Contact\Models\Contact;

class GetContact
{
    public function __construct(
        private readonly ContactPresenter $presenter,
    ) {}

    /**
     * Get a single contact with its addresses.
     *
     * @return array<string, mixed>
     */
    public function execute(int $id): array
    {
        $contact = Contact::with(['billingAddress', 'shippingAddress'])->findOrFail($id);

        return $this->presenter->serialize($contact);
    }
}
