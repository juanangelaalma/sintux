<?php

namespace Modules\Accounting\Application\Tax;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Tax;
use Modules\Accounting\Models\TaxGroupMember;

/**
 * Ganti-penuh daftar anggota grup (urutan array = position) dan hitung
 * ulang kolom rate turunan grup (Σ signed-rate; display-only).
 */
final class GroupMembership
{
    /**
     * @param  list<array{id: int, is_compound: bool}>  $members
     */
    public function sync(Tax $group, array $members): void
    {
        DB::transaction(function () use ($group, $members): void {
            TaxGroupMember::query()->where('tax_group_id', $group->id)->delete();

            $signedSum = 0.0;
            $position = 1;

            foreach ($members as $member) {
                TaxGroupMember::query()->create([
                    'tax_group_id' => $group->id,
                    'member_tax_id' => $member['id'],
                    'position' => $position,
                    'is_compound' => $member['is_compound'],
                ]);

                $model = Tax::query()->find($member['id']);
                $rate = abs((float) ($model->rate ?? 0));
                $signedSum += ($model && $model->is_withholding) ? -$rate : $rate;

                $position++;
            }

            $group->update(['rate' => $signedSum]);
        });
    }

    /**
     * @param  array<int, mixed>  $raw
     * @return list<array{id: int, is_compound: bool}>
     */
    public static function normalize(array $raw): array
    {
        return array_values(array_map(
            fn (array $member): array => [
                'id' => (int) $member['id'],
                'is_compound' => (bool) ($member['is_compound'] ?? false),
            ],
            $raw
        ));
    }
}
