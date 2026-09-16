import { Head, Link, useForm } from '@inertiajs/react';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import MultiSelect from '@/components/ui/multi-select';
import PageHeader from '@/components/ui/page-header';
import { SearchableSelect } from '@/components/ui/searchable-select';
import TextInput from '@/components/ui/text-input';
import SettingsLayout from '@/layouts/settings/layout';
import { formatRate } from './types';
import type { AccountOption, SingleTaxOption } from './types';

type Props = {
    accounts: AccountOption[];
    singleTaxes: SingleTaxOption[];
};

type MemberDraft = {
    id: number;
    is_compound: boolean;
};

export default function Create({ accounts, singleTaxes }: Props) {
    const { data, setData, post, transform, processing, errors } = useForm({
        type: 'single' as 'single' | 'group',
        name: '',
        code: '',
        rate: '',
        is_withholding: false,
        dpp_multiplier: false,
        output_account_id: '' as number | '',
        input_account_id: '' as number | '',
        members: [] as MemberDraft[],
        is_active: true,
    });

    const accountOptions = accounts.map((a) => ({
        id: a.id,
        label: `${a.code} — ${a.name}`,
    }));

    const singleById = new Map(singleTaxes.map((t) => [t.id, t]));
    const hasMultiplierMember = data.members.some(
        (m) => singleById.get(m.id)?.dpp_multiplier,
    );
    const effectiveRate = data.members.reduce(
        (sum, m) => sum + Number(singleById.get(m.id)?.signed_rate ?? 0),
        0,
    );

    const setType = (type: 'single' | 'group') => {
        setData('type', type);
    };

    const toggleFlag = (flag: 'is_withholding' | 'dpp_multiplier') => {
        const other =
            flag === 'is_withholding' ? 'dpp_multiplier' : 'is_withholding';

        setData({
            ...data,
            [flag]: !data[flag],
            [other]: false,
        });
    };

    const toggleMember = (ids: number[]) => {
        const current = new Map(data.members.map((m) => [m.id, m]));
        const next: MemberDraft[] = [];

        for (const id of ids) {
            next.push(current.get(id) ?? { id, is_compound: false });
        }

        setData('members', next);
    };

    const moveMember = (index: number, direction: -1 | 1) => {
        const next = [...data.members];
        const target = index + direction;

        if (target < 0 || target >= next.length) {
            return;
        }

        const [moved] = next.splice(index, 1);
        next.splice(target, 0, moved);
        setData('members', next);
    };

    const setCompound = (index: number, value: boolean) => {
        const next = [...data.members];
        next[index] = { ...next[index], is_compound: value };
        setData('members', next);
    };

    const removeMember = (index: number) => {
        setData(
            'members',
            data.members.filter((_, i) => i !== index),
        );
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        transform((payload) => {
            if (payload.type === 'single') {
                return {
                    type: payload.type,
                    name: payload.name,
                    code: payload.code,
                    rate: payload.rate === '' ? '' : Number(payload.rate),
                    is_withholding: payload.is_withholding,
                    dpp_multiplier: payload.dpp_multiplier,
                    input_account_id:
                        payload.input_account_id === ''
                            ? null
                            : Number(payload.input_account_id),
                    output_account_id:
                        payload.output_account_id === ''
                            ? null
                            : Number(payload.output_account_id),
                    is_active: payload.is_active,
                };
            }

            return {
                type: payload.type,
                name: payload.name,
                code: payload.code,
                members: payload.members,
                is_active: payload.is_active,
            };
        });

        post('/accounting/taxes', {
            preserveScroll: true,
        });
    };

    return (
        <SettingsLayout>
            <Head title="Buat Pajak Baru" />

            <div className="max-w-3xl space-y-6">
                <PageHeader
                    title="Buat Pajak Baru"
                    description="Pilih tipe pajak satuan atau grup, lalu lengkapi tarif dan pemetaan akunnya."
                />

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900"
                >
                    <FormField label="Tipe pajak" required>
                        <div className="flex gap-4">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="type"
                                    checked={data.type === 'single'}
                                    onChange={() => setType('single')}
                                    className="size-4"
                                />
                                Satuan
                            </label>
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="type"
                                    checked={data.type === 'group'}
                                    onChange={() => setType('group')}
                                    className="size-4"
                                />
                                Grup
                            </label>
                        </div>
                    </FormField>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField label="Nama" required error={errors.name}>
                            <TextInput
                                required
                                maxLength={100}
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Contoh: PPN"
                            />
                        </FormField>

                        <FormField label="Kode" required error={errors.code}>
                            <TextInput
                                required
                                maxLength={30}
                                value={data.code}
                                onChange={(e) =>
                                    setData('code', e.target.value)
                                }
                                placeholder="Contoh: PPN"
                            />
                        </FormField>
                    </div>

                    {data.type === 'single' ? (
                        <>
                            <FormField
                                label="Persentase Efektif"
                                required
                                error={errors.rate}
                            >
                                <TextInput
                                    required
                                    type="number"
                                    min={0}
                                    max={100}
                                    step="0.01"
                                    value={data.rate}
                                    onChange={(e) =>
                                        setData('rate', e.target.value)
                                    }
                                    placeholder="Contoh: 12"
                                />
                            </FormField>

                            <div className="space-y-2">
                                <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <input
                                        type="checkbox"
                                        checked={data.dpp_multiplier}
                                        onChange={() =>
                                            toggleFlag('dpp_multiplier')
                                        }
                                        className="size-4"
                                    />
                                    Pengali 11/12 (DPP Nilai Lain)
                                </label>
                                <div>
                                    <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                        <input
                                            type="checkbox"
                                            checked={data.is_withholding}
                                            onChange={() =>
                                                toggleFlag('is_withholding')
                                            }
                                            className="size-4"
                                        />
                                        Pemotongan (PPh)
                                    </label>
                                    <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Kalkulasi pemotongan menyusul — grup
                                        berisi pemotongan tidak muncul di
                                        transaksi.
                                    </p>
                                </div>
                                {(errors as Record<string, string>)
                                    .dpp_multiplier && (
                                    <p className="text-xs text-rose-600">
                                        {
                                            (errors as Record<string, string>)
                                                .dpp_multiplier
                                        }
                                    </p>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <FormField
                                    label="Akun Pajak Penjualan"
                                    error={errors.output_account_id}
                                >
                                    <SearchableSelect
                                        options={accountOptions}
                                        value={
                                            data.output_account_id === ''
                                                ? null
                                                : Number(data.output_account_id)
                                        }
                                        onChange={(v) =>
                                            setData(
                                                'output_account_id',
                                                v ?? '',
                                            )
                                        }
                                        placeholder="Pilih akun penjualan"
                                    />
                                </FormField>

                                <FormField
                                    label="Akun Pajak Pembelian"
                                    error={errors.input_account_id}
                                >
                                    <SearchableSelect
                                        options={accountOptions}
                                        value={
                                            data.input_account_id === ''
                                                ? null
                                                : Number(data.input_account_id)
                                        }
                                        onChange={(v) =>
                                            setData('input_account_id', v ?? '')
                                        }
                                        placeholder="Pilih akun pembelian"
                                    />
                                </FormField>
                            </div>
                        </>
                    ) : (
                        <>
                            <FormField
                                label="Anggota grup"
                                required
                                error={errors.members}
                            >
                                <MultiSelect
                                    values={data.members.map((m) => m.id)}
                                    options={singleTaxes.map((t) => ({
                                        value: t.id,
                                        label: `${t.name} (${formatRate(t.signed_rate)})`,
                                    }))}
                                    onChange={toggleMember}
                                    placeholder="Pilih pajak satuan anggota"
                                />
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Urutan anggota = urutan print/kalkulasi.
                                    Hanya pajak satuan aktif yang bisa dipilih.
                                </p>
                            </FormField>

                            {data.members.length > 0 && (
                                <div className="space-y-2">
                                    {data.members.map((member, index) => {
                                        const single = singleById.get(
                                            member.id,
                                        );

                                        if (!single) {
                                            return null;
                                        }

                                        const compoundDisabled =
                                            hasMultiplierMember &&
                                            !member.is_compound;

                                        return (
                                            <div
                                                key={member.id}
                                                className="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700"
                                            >
                                                <span className="font-medium text-gray-900 tabular-nums dark:text-white">
                                                    {index + 1}.
                                                </span>
                                                <span className="min-w-0 flex-1 font-medium text-gray-800 dark:text-gray-200">
                                                    {single.name}{' '}
                                                    <span className="text-gray-500 tabular-nums">
                                                        (
                                                        {formatRate(
                                                            single.signed_rate,
                                                        )}
                                                        )
                                                    </span>
                                                </span>
                                                <label
                                                    className="flex items-center gap-1.5 text-xs text-gray-600 dark:text-gray-300"
                                                    title={
                                                        compoundDisabled
                                                            ? 'Pajak majemuk tidak bisa dipakai bersama anggota dengan pengali 11/12.'
                                                            : undefined
                                                    }
                                                >
                                                    <input
                                                        type="checkbox"
                                                        checked={
                                                            member.is_compound
                                                        }
                                                        disabled={
                                                            hasMultiplierMember
                                                        }
                                                        onChange={(e) =>
                                                            setCompound(
                                                                index,
                                                                e.target
                                                                    .checked,
                                                            )
                                                        }
                                                        className="size-4"
                                                    />
                                                    Majemuk
                                                </label>
                                                <div className="flex gap-1">
                                                    <button
                                                        type="button"
                                                        disabled={index === 0}
                                                        onClick={() =>
                                                            moveMember(
                                                                index,
                                                                -1,
                                                            )
                                                        }
                                                        aria-label={`Naikkan ${single.name}`}
                                                        className="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 disabled:opacity-30 dark:text-gray-300 dark:hover:bg-gray-800"
                                                    >
                                                        ↑
                                                    </button>
                                                    <button
                                                        type="button"
                                                        disabled={
                                                            index ===
                                                            data.members
                                                                .length -
                                                                1
                                                        }
                                                        onClick={() =>
                                                            moveMember(index, 1)
                                                        }
                                                        aria-label={`Turunkan ${single.name}`}
                                                        className="rounded px-2 py-1 text-xs text-gray-600 hover:bg-gray-100 disabled:opacity-30 dark:text-gray-300 dark:hover:bg-gray-800"
                                                    >
                                                        ↓
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            removeMember(index)
                                                        }
                                                        aria-label={`Hapus ${single.name} dari grup`}
                                                        className="rounded px-2 py-1 text-xs text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/30"
                                                    >
                                                        ✕
                                                    </button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}

                            <div className="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-800/50">
                                <span className="text-gray-600 dark:text-gray-300">
                                    Σ Persentase Efektif:{' '}
                                </span>
                                <span className="font-semibold text-gray-900 tabular-nums dark:text-white">
                                    {formatRate(effectiveRate)}
                                </span>{' '}
                                <span className="text-xs text-gray-500">
                                    (read-only, otomatis)
                                </span>
                            </div>
                        </>
                    )}

                    <FormField label="Status">
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                                className="size-4"
                            />
                            Aktif
                        </label>
                    </FormField>

                    <FormActions
                        submitLabel="Buat Pajak"
                        cancelLabel="Batal"
                        processing={processing}
                        onCancel={() => window.history.back()}
                    />
                </form>

                <Link
                    href="/accounting/taxes"
                    className="text-sm font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                >
                    ← Kembali ke daftar pajak
                </Link>
            </div>
        </SettingsLayout>
    );
}
