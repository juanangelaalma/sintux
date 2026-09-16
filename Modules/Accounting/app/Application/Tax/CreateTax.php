<?php

namespace Modules\Accounting\Application\Tax;

use Modules\Accounting\Models\Tax;

class CreateTax
{
    public function __construct(
        private readonly TaxRules $rules,
        private readonly GroupMembership $membership,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Tax
    {
        if (($data['type'] ?? Tax::TYPE_SINGLE) === Tax::TYPE_GROUP) {
            return $this->createGroup($data);
        }

        return $this->createSingle($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSingle(array $data): Tax
    {
        $this->rules->assertSingle($data);

        return Tax::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'rate' => (float) $data['rate'],
            'type' => Tax::TYPE_SINGLE,
            'is_withholding' => (bool) ($data['is_withholding'] ?? false),
            'dpp_multiplier' => (bool) ($data['dpp_multiplier'] ?? false),
            'input_account_id' => $data['input_account_id'] ?? null,
            'output_account_id' => $data['output_account_id'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createGroup(array $data): Tax
    {
        $members = GroupMembership::normalize($data['members'] ?? []);
        $this->rules->assertGroup($data, $members);

        $tax = Tax::query()->create([
            'name' => $data['name'],
            'code' => $data['code'],
            'rate' => 0,
            'type' => Tax::TYPE_GROUP,
            'is_withholding' => false,
            'dpp_multiplier' => false,
            'input_account_id' => null,
            'output_account_id' => null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        $this->membership->sync($tax, $members);

        return $tax;
    }
}
