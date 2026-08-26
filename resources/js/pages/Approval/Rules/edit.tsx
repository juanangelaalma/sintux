import { Head, useForm } from '@inertiajs/react';
import React from 'react';
import FormActions from '@/components/ui/form-actions';
import FormField from '@/components/ui/form-field';
import MultiSelect from '@/components/ui/multi-select';
import PageHeader from '@/components/ui/page-header';
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

type Rule = {
    id: number;
    name: string;
    description?: string | null;
    currency_code: string;
    scope_all_users: boolean;
    apply_to_existing_draft: boolean;
    is_active: boolean;
    pending_mappings_count: number;
    transaction_type_id: number;
    transaction_type: TransactionType;
    criteria: Array<{ id: number; min_amount: string }>;
    stages: Array<{
        id: number;
        stage_order: number;
        approval_type: 'any' | 'all';
        approvers: Array<{ id: number }>;
    }>;
    scoped_users: Array<{ id: number }>;
};

type Props = {
    rule: Rule;
    users: UserOption[];
};

type StageForm = {
    approval_type: 'any' | 'all';
    approver_ids: number[];
};

export default function Edit({ rule, users }: Props) {
    const isLocked = rule.pending_mappings_count > 0;

    const initialMinAmount = rule.criteria?.[0]?.min_amount
        ? String(Number(rule.criteria[0].min_amount))
        : '';

    const initialStages: StageForm[] = rule.stages.map((s) => ({
        approval_type: s.approval_type,
        approver_ids: s.approvers?.map((a) => a.id) ?? [],
    }));

    const { data, setData, put, processing, errors } = useForm({
        name: rule.name,
        description: rule.description ?? '',
        currency_code: rule.currency_code,
        scope_all_users: rule.scope_all_users,
        scoped_user_ids: rule.scoped_users?.map((u) => u.id) ?? [],
        apply_to_existing_draft: rule.apply_to_existing_draft,
        is_active: rule.is_active,
        min_amount: initialMinAmount,
        stages: initialStages,
    });

    const userOptions = users.map((u) => ({
        value: u.id,
        label: `${u.name} (${u.email})`,
    }));

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
        put(`/approval/rules/${rule.id}`);
    };

    return (
        <SettingsLayout>
            <Head title={`Edit Aturan: ${rule.name}`} />

            <div className="max-w-3xl space-y-6">
                <PageHeader
                    title={`Edit Aturan: ${rule.name}`}
                    description={
                        isLocked
                            ? 'Aturan memiliki transaksi draft yang belum selesai; hanya daftar approver yang dapat diubah.'
                            : 'Ubah konfigurasi aturan persetujuan.'
                    }
                />

                {isLocked && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300">
                        🔒 <strong>Aturan Terkunci:</strong> Terdapat{' '}
                        <strong>
                            {rule.pending_mappings_count} transaksi draft
                        </strong>{' '}
                        yang sedang berjalan menggunakan aturan ini. Kriteria
                        nominal, nama, dan tipe transaksi tidak dapat diubah
                        sampai seluruh transaksi draft selesai.
                    </div>
                )}

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
                                disabled={isLocked}
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </FormField>

                        <FormField label="Deskripsi" error={errors.description}>
                            <TextInput
                                type="text"
                                value={data.description}
                                disabled={isLocked}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                            />
                        </FormField>
                    </div>

                    <div className="space-y-4 border-t border-gray-100 pt-6 dark:border-gray-800">
                        <FormField label="Status Aturan">
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                                <input
                                    type="checkbox"
                                    checked={data.is_active}
                                    disabled={isLocked}
                                    onChange={(e) =>
                                        setData('is_active', e.target.checked)
                                    }
                                    className="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500"
                                />
                                Aktifkan aturan ini
                            </label>
                        </FormField>

                        <FormField
                            label="Jumlah minimal nominal yang dipicu"
                            error={errors.min_amount}
                            required
                        >
                            <TextInput
                                type="number"
                                step="0.01"
                                min="0.01"
                                value={data.min_amount}
                                disabled={isLocked}
                                onChange={(e) =>
                                    setData('min_amount', e.target.value)
                                }
                                required
                            />
                        </FormField>
                    </div>

                    <div className="space-y-4 border-t border-gray-100 pt-6 dark:border-gray-800">
                        <div>
                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white">
                                Tingkatan approval
                            </h4>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Anda dapat mengubah daftar approver per tahap.
                            </p>
                        </div>

                        {data.stages.map((stage, idx) => (
                            <div
                                key={idx}
                                className="space-y-3 rounded-xl border border-gray-200 bg-gray-50/50 p-4 dark:border-gray-800 dark:bg-gray-800/30"
                            >
                                <h5 className="text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                    Approval Tingkat {idx + 1} (
                                    {stage.approval_type === 'any'
                                        ? 'Salah Satu'
                                        : 'Semua'}
                                    )
                                </h5>

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
                    </div>

                    <FormActions
                        onCancel={() => history.back()}
                        submitLabel="Simpan Perubahan"
                        processing={processing}
                    />
                </form>
            </div>
        </SettingsLayout>
    );
}
