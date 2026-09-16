<?php

namespace Modules\Accounting\Application\Tax;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Tax;

/**
 * Aturan bisnis definisi pajak (dipakai Create & Update).
 * FormRequest memvalidasi bentuk input; ini penjaga kebenaran semantik.
 */
final class TaxRules
{
    /**
     * @param  array{code?: string, name?: string, rate?: float|int|string, is_withholding?: bool, dpp_multiplier?: bool, input_account_id?: int|null, output_account_id?: int|null}  $data
     */
    public function assertSingle(array $data, ?int $ignoreId = null): void
    {
        $this->assertUnique($data, $ignoreId);

        $rate = (float) ($data['rate'] ?? 0);

        if ($rate < 0 || $rate > 100) {
            throw ValidationException::withMessages(['rate' => 'Tarif pajak harus antara 0 sampai 100.']);
        }

        if (! empty($data['is_withholding']) && ! empty($data['dpp_multiplier'])) {
            throw ValidationException::withMessages([
                'dpp_multiplier' => 'Pajak pemotongan tidak bisa memakai pengali 11/12 untuk DPP.',
            ]);
        }

        if (empty($data['input_account_id']) && empty($data['output_account_id'])) {
            throw ValidationException::withMessages([
                'output_account_id' => 'Petakan minimal satu akun pajak (penjualan atau pembelian).',
            ]);
        }
    }

    /**
     * @param  list<array{id: int, is_compound: bool}>  $members
     */
    public function assertGroup(array $data, array $members, ?int $ignoreId = null): void
    {
        $this->assertUnique($data, $ignoreId);

        if ($members === []) {
            throw ValidationException::withMessages(['members' => 'Grup pajak membutuhkan minimal satu pajak satuan.']);
        }

        $ids = array_column($members, 'id');

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages(['members' => 'Anggota grup tidak boleh duplikat.']);
        }

        $taxes = Tax::query()->whereIn('id', $ids)->get()->keyBy('id');

        foreach ($ids as $memberId) {
            $member = $taxes->get($memberId);

            if (! $member) {
                throw ValidationException::withMessages(['members' => 'Salah satu pajak anggota tidak ditemukan.']);
            }

            if ($member->type === Tax::TYPE_GROUP) {
                throw ValidationException::withMessages(['members' => 'Grup pajak tidak boleh berisi grup lain.']);
            }

            if (! $member->is_active) {
                throw ValidationException::withMessages(['members' => "Pajak \"{$member->name}\" sudah nonaktif dan tidak bisa jadi anggota."]);
            }
        }

        // Aturan Jurnal: Majemuk tidak sah bila ada anggota ber-pengali 11/12.
        $hasMultiplier = $taxes->contains(fn (Tax $member): bool => $member->dpp_multiplier);

        if ($hasMultiplier) {
            foreach ($members as $member) {
                if ($member['is_compound']) {
                    throw ValidationException::withMessages([
                        'members' => 'Pajak majemuk tidak bisa dipakai bersama anggota dengan pengali 11/12.',
                    ]);
                }
            }
        }
    }

    /**
     * @param  array{code?: string, name?: string}  $data
     */
    private function assertUnique(array $data, ?int $ignoreId): void
    {
        $nameExists = Tax::query()
            ->where('name', $data['name'] ?? '')
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($nameExists) {
            throw ValidationException::withMessages(['name' => 'Nama pajak sudah dipakai.']);
        }

        $codeExists = Tax::query()
            ->where('code', $data['code'] ?? '')
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        if ($codeExists) {
            throw ValidationException::withMessages(['code' => 'Kode pajak sudah dipakai.']);
        }
    }
}
