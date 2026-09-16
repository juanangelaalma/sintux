<?php

namespace Modules\Accounting\Application\Tax;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Models\TaxGroupMember;

class UpdateTax
{
    public function __construct(
        private readonly TaxRules $rules,
        private readonly TaxReferences $references,
        private readonly GroupMembership $membership,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(int $id, array $data): Tax
    {
        $tax = Tax::query()->with('groupMembers')->findOrFail($id);

        if (isset($data['type']) && $data['type'] !== $tax->type) {
            throw ValidationException::withMessages(['type' => 'Tipe pajak tidak dapat diubah.']);
        }

        $inUse = $this->references->inUse($tax->id);

        return $tax->type === Tax::TYPE_GROUP
            ? $this->updateGroup($tax, $data, $inUse)
            : $this->updateSingle($tax, $data, $inUse);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateSingle(Tax $tax, array $data, bool $inUse): Tax
    {
        if ($inUse) {
            $this->guardLockedScalarFields($tax, $data);
            $tax->update($this->allowedPayload($data));

            return $tax;
        }

        $this->rules->assertSingle($data, $tax->id);

        $tax->update([
            'name' => $data['name'] ?? $tax->name,
            'code' => $data['code'] ?? $tax->code,
            'rate' => (float) ($data['rate'] ?? $tax->rate),
            'is_withholding' => (bool) ($data['is_withholding'] ?? $tax->is_withholding),
            'dpp_multiplier' => (bool) ($data['dpp_multiplier'] ?? $tax->dpp_multiplier),
            'input_account_id' => array_key_exists('input_account_id', $data) ? ($data['input_account_id'] ?: null) : $tax->input_account_id,
            'output_account_id' => array_key_exists('output_account_id', $data) ? ($data['output_account_id'] ?: null) : $tax->output_account_id,
            'is_active' => (bool) ($data['is_active'] ?? $tax->is_active),
        ]);

        return $tax;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function updateGroup(Tax $tax, array $data, bool $inUse): Tax
    {
        if ($inUse) {
            if (isset($data['members']) && $this->membersDiffer($tax, GroupMembership::normalize($data['members']))) {
                throw ValidationException::withMessages([
                    'members' => 'Grup sedang terpakai — hanya nama dan status yang dapat diubah.',
                ]);
            }

            if (isset($data['code']) && $data['code'] !== $tax->code) {
                throw ValidationException::withMessages([
                    'code' => 'Pajak sedang terpakai — hanya nama dan status yang dapat diubah.',
                ]);
            }

            $tax->update($this->allowedPayload($data));

            return $tax;
        }

        $members = GroupMembership::normalize($data['members'] ?? []);
        $this->rules->assertGroup($data, $members, $tax->id);

        $tax->update([
            'name' => $data['name'] ?? $tax->name,
            'code' => $data['code'] ?? $tax->code,
            'is_active' => (bool) ($data['is_active'] ?? $tax->is_active),
        ]);

        $this->membership->sync($tax, $members);

        return $tax->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardLockedScalarFields(Tax $tax, array $data): void
    {
        $locked = [
            'code' => fn (mixed $incoming): bool => (string) $incoming !== (string) $tax->code,
            'rate' => fn (mixed $incoming): bool => abs((float) $incoming - (float) $tax->rate) > 0.0001,
            'is_withholding' => fn (mixed $incoming): bool => (bool) $incoming !== (bool) $tax->is_withholding,
            'dpp_multiplier' => fn (mixed $incoming): bool => (bool) $incoming !== (bool) $tax->dpp_multiplier,
            'input_account_id' => fn (mixed $incoming): bool => ($incoming ?: null) !== $tax->input_account_id,
            'output_account_id' => fn (mixed $incoming): bool => ($incoming ?: null) !== $tax->output_account_id,
        ];

        foreach ($locked as $field => $differs) {
            if (array_key_exists($field, $data) && $differs($data[$field])) {
                throw ValidationException::withMessages([
                    $field => 'Pajak sedang terpakai — hanya nama dan status yang dapat diubah.',
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function allowedPayload(array $data): array
    {
        $payload = [];

        if (isset($data['name'])) {
            $payload['name'] = $data['name'];
        }

        if (array_key_exists('is_active', $data)) {
            $payload['is_active'] = (bool) $data['is_active'];
        }

        return $payload;
    }

    /**
     * @param  list<array{id: int, is_compound: bool}>  $incoming
     */
    private function membersDiffer(Tax $tax, array $incoming): bool
    {
        $current = $tax->groupMembers
            ->sortBy(fn (TaxGroupMember $member): int => $member->position)
            ->map(fn (TaxGroupMember $member): array => [
                'id' => $member->member_tax_id,
                'is_compound' => $member->is_compound,
            ])
            ->values()
            ->all();

        return $current !== $incoming;
    }
}
