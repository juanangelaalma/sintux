<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Models\Tax;
use Modules\Accounting\Models\TaxGroupMember;

/**
 * Public tax API for consuming modules (Purchasing, Sales, Product).
 *
 * Eligibility rules:
 * - Satuan: aktif, BUKAN pemotongan, akun sisi tsb (jual/beli) terpetakan.
 * - Grup: aktif, punya ≥1 anggota, SEMUA anggota satuan aktif, bukan
 *   pemotongan, dan ber-akun di sisi tsb. Grup beranggota pemotongan sengaja
 *   tidak ditawarkan sampai kalkulasi pemotongan tersedia.
 *
 * Projection only — model internals never leak.
 */
final class TaxQuery
{
    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     rate: float,
     *     type: string,
     *     dpp_multiplier: bool,
     *     members?: list<array{id: int, name: string, signed_rate: float, is_compound: bool, position: int, dpp_multiplier: bool}>
     * }>
     */
    public function listForPurchase(): array
    {
        return $this->listEligible('input_account_id');
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string,
     *     name: string,
     *     rate: float,
     *     type: string,
     *     dpp_multiplier: bool,
     *     members?: list<array{id: int, name: string, signed_rate: float, is_compound: bool, position: int, dpp_multiplier: bool}>
     * }>
     */
    public function listForSale(): array
    {
        return $this->listEligible('output_account_id');
    }

    public function isEligibleForPurchase(int $taxId): bool
    {
        return $this->isEligible($taxId, 'input_account_id');
    }

    public function isEligibleForSale(int $taxId): bool
    {
        return $this->isEligible($taxId, 'output_account_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listEligible(string $accountColumn): array
    {
        $taxes = Tax::query()
            ->where('is_active', true)
            ->with(['groupMembers.member'])
            ->orderBy('code')
            ->get();

        $rows = [];

        foreach ($taxes as $tax) {
            if (! $this->taxIsEligible($tax, $accountColumn)) {
                continue;
            }

            $row = [
                'id' => $tax->id,
                'code' => $tax->code,
                'name' => $tax->name,
                'rate' => $this->effectiveRate($tax),
                'type' => $tax->type ?? Tax::TYPE_SINGLE,
                'dpp_multiplier' => $tax->dpp_multiplier,
            ];

            if ($tax->type === Tax::TYPE_GROUP) {
                $row['members'] = $this->memberProjection($tax);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    private function isEligible(int $taxId, string $accountColumn): bool
    {
        $tax = Tax::query()
            ->with(['groupMembers.member'])
            ->whereKey($taxId)
            ->where('is_active', true)
            ->first();

        return $tax !== null && $this->taxIsEligible($tax, $accountColumn);
    }

    private function taxIsEligible(Tax $tax, string $accountColumn): bool
    {
        if ($tax->type === Tax::TYPE_GROUP) {
            $members = $tax->groupMembers;

            if ($members->isEmpty()) {
                return false;
            }

            foreach ($members as $groupMember) {
                $member = $groupMember->member;

                if (! $member || ! $member->is_active || $member->is_withholding || $member->{$accountColumn} === null) {
                    return false;
                }
            }

            return true;
        }

        return ! $tax->is_withholding && $tax->{$accountColumn} !== null;
    }

    /**
     * Σ signed-rate untuk grup; rate positif apa adanya untuk satuan.
     */
    private function effectiveRate(Tax $tax): float
    {
        if ($tax->type !== Tax::TYPE_GROUP) {
            return abs((float) $tax->rate);
        }

        return $tax->groupMembers->sum(
            fn (TaxGroupMember $groupMember): float => $this->signedRate($groupMember->member)
        );
    }

    /**
     * @return list<array{id: int, name: string, signed_rate: float, is_compound: bool, position: int, dpp_multiplier: bool}>
     */
    private function memberProjection(Tax $tax): array
    {
        return $tax->groupMembers
            ->sortBy(fn (TaxGroupMember $groupMember): int => $groupMember->position)
            ->map(fn (TaxGroupMember $groupMember): array => [
                'id' => $groupMember->member->id,
                'name' => $groupMember->member->name,
                'signed_rate' => $this->signedRate($groupMember->member),
                'is_compound' => $groupMember->is_compound,
                'position' => $groupMember->position,
                'dpp_multiplier' => $groupMember->member->dpp_multiplier,
            ])
            ->values()
            ->all();
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
