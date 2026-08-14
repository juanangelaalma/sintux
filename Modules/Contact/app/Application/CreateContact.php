<?php

namespace Modules\Contact\Application;

use Illuminate\Support\Facades\DB;
use Modules\Contact\Models\Contact;

class CreateContact
{
    public function __construct(
        private readonly SyncContactAddresses $syncContactAddresses,
    ) {}

    /**
     * Create a new contact for the active tenant.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(string $type, array $data): Contact
    {
        return DB::transaction(function () use ($type, $data) {
            $contact = Contact::create([
                'branch_id' => $data['branch_id'],
                'type' => $type,
                'name' => $data['name'],
                'registered_at' => $data['registered_at'] ?? now()->toDateString(),
                'tier_relation' => $type === 'customer' ? ($data['tier_relation'] ?? null) : null,
                'identity_type' => $data['identity_type'] ?? null,
                'identity_number' => $data['identity_number'] ?? null,
                'company_name' => $data['company_name'] ?? null,
                'email' => $data['email'] ?? null,
                'mobile_phone' => $data['mobile_phone'] ?? null,
                'telephone' => $data['telephone'] ?? null,
                'fax' => $data['fax'] ?? null,
                'npwp' => $data['npwp'] ?? null,
                'notes' => $data['notes'] ?? null,
                'bank_name' => $data['bank_name'] ?? null,
                'bank_branch' => $data['bank_branch'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'shipping_same_as_billing' => $data['shipping_same_as_billing'] ?? true,
            ]);

            $this->syncContactAddresses->execute($contact, $data);

            return $contact;
        });
    }
}
