<?php

namespace Modules\Accounting\Application\Tax;

use Modules\Accounting\Models\Tax;
use Modules\Accounting\Models\TaxGroupMember;

class GetTaxes
{
    public function __construct(
        private readonly TaxReferences $references,
    ) {}

    /**
     * Daftar semua pajak (aktif + nonaktif) lengkap flag in_use.
     *
     * @return list<array<string, mixed>>
     */
    public function execute(): array
    {
        $taxes = Tax::query()
            ->with([
                'inputAccount:id,name',
                'outputAccount:id,name',
                'groupMembers' => fn ($q) => $q->with('member:id,name,rate,is_withholding')->orderBy('position'),
            ])
            ->orderBy('code')
            ->get();

        return $taxes
            ->map(fn (Tax $tax): array => $this->serialize($tax))
            ->values()
            ->all();
    }

    public function find(int $id): array
    {
        $tax = Tax::query()
            ->with([
                'inputAccount:id,name',
                'outputAccount:id,name',
                'groupMembers' => fn ($q) => $q->with('member:id,name,rate,is_withholding')->orderBy('position'),
            ])
            ->findOrFail($id);

        return $this->serialize($tax);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Tax $tax): array
    {
        $members = $tax->type === Tax::TYPE_GROUP
            ? $tax->groupMembers->map(fn (TaxGroupMember $groupMember): array => [
                'id' => $groupMember->member?->id,
                'name' => $groupMember->member?->name,
                'rate' => (float) ($groupMember->member?->rate ?? 0),
                'signed_rate' => $this->signedRate($groupMember->member),
                'is_compound' => $groupMember->is_compound,
                'position' => $groupMember->position,
            ])->values()->all()
            : [];

        $effectiveRate = $tax->type === Tax::TYPE_GROUP
            ? array_sum(array_column($members, 'signed_rate'))
            : (float) $tax->rate;

        return [
            'id' => $tax->id,
            'name' => $tax->name,
            'code' => $tax->code,
            'type' => $tax->type,
            'rate' => $effectiveRate,
            'is_withholding' => $tax->is_withholding,
            'dpp_multiplier' => $tax->dpp_multiplier,
            'sales_account' => $tax->outputAccount ? ['id' => $tax->outputAccount->id, 'name' => $tax->outputAccount->name] : null,
            'purchase_account' => $tax->inputAccount ? ['id' => $tax->inputAccount->id, 'name' => $tax->inputAccount->name] : null,
            'is_active' => $tax->is_active,
            'members' => $members,
            'in_use' => $this->references->inUse($tax->id),
        ];
    }

    private function signedRate(?Tax $member): float
    {
        if (! $member) {
            return 0.0;
        }

        $rate = abs((float) $member->rate);

        return $member->is_withholding ? -$rate : $rate;
    }
}
