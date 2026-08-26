import { Head, router, useForm } from '@inertiajs/react';
import React from 'react';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import MultiSelect from '@/components/ui/multi-select';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import SettingsLayout from '@/layouts/settings/layout';

type TransactionType = {
    id: number;
    module: string;
    key: string;
    label: string;
};

type UserOption = {
    id: number;
    name: string;
    email: string;
};

type Props = {
    transactionTypes: TransactionType[];
    users: UserOption[];
};

type StageForm = {
    approval_type: 'any' | 'all';
    approver_ids: number[];
};

export default function Create({ transactionTypes, users }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: '',
        transaction_type_id: transactionTypes[0]?.id ?? 0,
        currency_code: 'IDR',
        scope_all_users: true,
        scoped_user_ids: [] as number[],
        apply_to_existing_draft: true,
        min_amount: '',
        stages: [
            { approval_type: 'any' as const, approver_ids: [] as number[] },
        ] as StageForm[],
    });

    const userOptions = users.map((u) => ({
        value: u.id,
        label: `${u.name} (${u.email})`,
    }));

    const handleAddStage = () => {
        if (data.stages.length >= 2) {
            return;
        }

        setData('stages', [
            ...data.stages,
            { approval_type: 'any', approver_ids: [] },
        ]);
    };

    const handleRemoveStage = (index: number) => {
        if (data.stages.length <= 1) {
            return;
        }

        setData(
            'stages',
            data.stages.filter((_, i) => i !== index),
        );
    };

    const handleStageChange = (
        index: number,
        field: keyof StageForm,
        value: any,
    ) => {
        const updated = [...data.stages];
        updated[index] = { ...updated[index], [field]: value };
        setData('stages', updated);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/approval/rules');
    };

    const selectedType = transactionTypes.find(
        (t) => t.id === Number(data.transaction_type_id),
    );

    return (
        <SettingsLayout>
            <Head title="Buat Aturan Approval" />

            <div className="max-w-3xl space-y-6">
                <PageHeader
                    title="Buat Aturan Approval"
                    description="Definisikan kondisi dan alur persetujuan berlapis untuk transaksi."
                />

                <form
                    onSubmit={handleSubmit}
                    className="space-y-8 rounded-xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900"
                >
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField
                            label="Nama aturan"
                            error={errors.name}
                            required
                        >
                            <TextInput
                                type="text"
                                value={data.name}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                placeholder="Contoh: Purchase Order Rule > 10Jt"
                                required
                            />
                        </FormField>

                        <FormField label="Deskripsi" error={errors.description}>
                            <TextInput
                                type="text"
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                placeholder="Opsional"
                            />
                        </FormField>
                    </div>

                    <div className="space-y-4 border-t border-gray-100 pt-6 dark:border-gray-800">
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                                Kondisi peraturan
                            </h4>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Tentukan kondisi transaksi yang membutuhkan
                                approval.
                            </p>
                        </div>

                        <FormField
                            label="Tipe transaksi"
                            error={errors.transaction_type_id}
                            required
                        >
                            <SelectInput
                                value={data.transaction_type_id}
                                onChange={(e) =>
                                    setData(
                                        'transaction_type_id',
                                        Number(e.target.value),
                                    )
                                }
                            >
                                {transactionTypes.map((t) => (
                                    <option key={t.id} value={t.id}>
                                        {t.label} ({t.module})
                                    </option>
                                ))}
                            </SelectInput>
                        </FormField>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField label="Mata Uang">
                                <SelectInput
                                    value={data.currency_code}
                                    onChange={(e) =>
                                        setData('currency_code', e.target.value)
                                    }
                                >
                                    <option value="IDR">IDR</option>
                                </SelectInput>
                            </FormField>

                            <div className="md:col-span-2">
                                <FormField
                                    label={`Jumlah ${selectedType?.label ?? 'transaksi'} yang besar dari`}
                                    error={errors.min_amount}
                                    required
                                >
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={data.min_amount}
                                        onChange={(e) =>
                                            setData(
                                                'min_amount',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0,00"
                                        required
                                    />
                                </FormField>
                            </div>
                        </div>

                        <p className="rounded-lg bg-amber-50 p-2.5 text-xs text-amber-600 dark:bg-amber-950/30 dark:text-amber-400">
                            💡 Tips: Masukkan nilai terkecil jika Anda ingin
                            membuat lebih dari satu aturan untuk tipe transaksi
                            yang sama.
                        </p>
                    </div>

                    <div className="space-y-3 border-t border-gray-100 pt-6 dark:border-gray-800">
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                                Transaksi dibuat oleh
                            </h4>
                        </div>

                        <div className="flex items-center gap-6">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="scope_all_users"
                                    checked={data.scope_all_users}
                                    onChange={() =>
                                        setData('scope_all_users', true)
                                    }
                                    className="text-brand-600 focus:ring-brand-500"
                                />
                                Semua pengguna
                            </label>

                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="radio"
                                    name="scope_all_users"
                                    checked={!data.scope_all_users}
                                    onChange={() =>
                                        setData('scope_all_users', false)
                                    }
                                    className="text-brand-600 focus:ring-brand-500"
                                />
                                Pengguna tertentu
                            </label>
                        </div>

                        {!data.scope_all_users && (
                            <FormField
                                label="Pilih Pembuat Transaksi"
                                error={errors.scoped_user_ids}
                            >
                                <MultiSelect
                                    values={data.scoped_user_ids}
                                    options={userOptions}
                                    onChange={(vals) =>
                                        setData('scoped_user_ids', vals)
                                    }
                                    placeholder="Pilih pengguna..."
                                />
                            </FormField>
                        )}
                    </div>

                    <div className="space-y-4 border-t border-gray-100 pt-6 dark:border-gray-800">
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                                Tingkatan approval
                            </h4>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Tentukan tingkatan approval berdasarkan jumlah
                                dan nama approver (maksimal 2 tahap).
                            </p>
                        </div>

                        {data.stages.map((stage, idx) => (
                            <div
                                key={idx}
                                className="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30"
                            >
                                <div className="flex items-center justify-between">
                                    <h5 className="text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                        Approval Tingkat {idx + 1}
                                    </h5>
                                    {data.stages.length > 1 && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleRemoveStage(idx)
                                            }
                                            className="text-xs font-medium text-rose-600 hover:text-rose-700"
                                        >
                                            Hapus Tahap
                                        </button>
                                    )}
                                </div>

                                <div className="grid grid-cols-1 items-center gap-4 md:grid-cols-3">
                                    <div className="text-xs text-gray-600 dark:text-gray-400">
                                        Transaksi memerlukan approval dari:
                                    </div>
                                    <div>
                                        <SelectInput
                                            value={stage.approval_type}
                                            onChange={(e) =>
                                                handleStageChange(
                                                    idx,
                                                    'approval_type',
                                                    e.target.value,
                                                )
                                            }
                                        >
                                            <option value="any">
                                                Salah satu approver
                                            </option>
                                            <option value="all">
                                                Semua approver
                                            </option>
                                        </SelectInput>
                                    </div>
                                    <div className="text-xs text-gray-500">
                                        di bawah ini
                                    </div>
                                </div>

                                <FormField
                                    label="Approver"
                                    error={
                                        errors[
                                            `stages.${idx}.approver_ids` as keyof typeof errors
                                        ]
                                    }
                                >
                                    <MultiSelect
                                        values={stage.approver_ids}
                                        options={userOptions}
                                        onChange={(vals) =>
                                            handleStageChange(
                                                idx,
                                                'approver_ids',
                                                vals,
                                            )
                                        }
                                        placeholder="Pilih approver..."
                                    />
                                </FormField>
                            </div>
                        ))}

                        {data.stages.length < 2 && (
                            <button
                                type="button"
                                onClick={handleAddStage}
                                className="inline-flex items-center text-xs font-semibold text-brand-600 hover:text-brand-700 dark:text-brand-400"
                            >
                                + Tambah tingkatan approval
                            </button>
                        )}
                    </div>

                    <div className="flex items-center justify-between border-t border-gray-100 pt-6 dark:border-gray-800">
                        <div>
                            <label className="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                                Penerapan aturan
                            </label>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Terapkan aturan ini ke draft transaksi yang
                                sudah ada
                            </p>
                        </div>
                        <input
                            type="checkbox"
                            checked={data.apply_to_existing_draft}
                            onChange={(e) =>
                                setData(
                                    'apply_to_existing_draft',
                                    e.target.checked,
                                )
                            }
                            className="h-5 w-5 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                        />
                    </div>

                    <FormActions
                        onCancel={() => router.get('/approval/rules')}
                        submitLabel="Simpan Aturan"
                        processing={processing}
                    />
                </form>
            </div>
        </SettingsLayout>
    );
}
