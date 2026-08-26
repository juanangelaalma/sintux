import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import SettingsLayout from '@/layouts/settings/layout';

type TransactionType = {
    id: number;
    module: string;
    key: string;
    label: string;
};

type Rule = {
    id: number;
    name: string;
    description?: string | null;
    currency_code: string;
    is_active: boolean;
    pending_mappings_count: number;
    transaction_type: TransactionType;
    criteria: Array<{ id: number; min_amount: string }>;
    stages: Array<{ id: number; stage_order: number; approval_type: string }>;
};

type Props = {
    rules: Rule[];
    transactionTypes: TransactionType[];
    filters: {
        transaction_type?: string;
        search?: string;
    };
};

export default function Index({ rules, transactionTypes, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedType, setSelectedType] = useState(
        filters.transaction_type ?? '',
    );

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/approval/rules',
            {
                search,
                transaction_type: selectedType,
            },
            { preserveState: true },
        );
    };

    const handleTypeChange = (key: string) => {
        setSelectedType(key);
        router.get(
            '/approval/rules',
            {
                search,
                transaction_type: key,
            },
            { preserveState: true },
        );
    };

    const handleDelete = (id: number, name: string) => {
        if (confirm(`Apakah Anda yakin ingin menghapus aturan "${name}"?`)) {
            router.delete(`/approval/rules/${id}`);
        }
    };

    return (
        <SettingsLayout>
            <Head title="Aturan Approval" />

            <div className="space-y-6">
                <PageHeader
                    title="Aturan Approval"
                    description="Kelola aturan persetujuan berlapis untuk transaksi keuangan dan operasional perusahaan."
                    actions={
                        <Link href="/approval/rules/create">
                            <Button variant="primary">
                                Buat Aturan Approval
                            </Button>
                        </Link>
                    }
                />

                <div className="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
                    <div className="flex items-center gap-2">
                        <select
                            value={selectedType}
                            onChange={(e) => handleTypeChange(e.target.value)}
                            className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        >
                            <option value="">Semua Tipe Transaksi</option>
                            {transactionTypes.map((t) => (
                                <option key={t.id} value={t.key}>
                                    {t.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <form
                        onSubmit={handleSearch}
                        className="flex items-center gap-2"
                    >
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari aturan..."
                            className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        />
                        <Button type="submit" variant="secondary">
                            Cari
                        </Button>
                    </form>
                </div>

                {rules.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                            <span className="text-xl">📋</span>
                        </div>
                        <h3 className="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                            Belum ada aturan approval
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Aturan approval yang Anda buat akan muncul di sini.
                        </p>
                        <div className="mt-6">
                            <Link href="/approval/rules/create">
                                <Button variant="primary">
                                    Buat Aturan Approval
                                </Button>
                            </Link>
                        </div>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-gray-200 bg-gray-50/50 text-xs text-gray-500 uppercase dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Nama Aturan
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Tipe Transaksi
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Min Nominal
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Jumlah Tahap
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Draft Pending
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Status
                                    </th>
                                    <th className="px-6 py-3.5 text-right font-semibold">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                {rules.map((rule) => {
                                    const minAmount = rule.criteria?.[0]
                                        ?.min_amount
                                        ? Number(
                                              rule.criteria[0].min_amount,
                                          ).toLocaleString('id-ID')
                                        : '0';

                                    return (
                                        <tr
                                            key={rule.id}
                                            className="hover:bg-gray-50/50 dark:hover:bg-gray-800/50"
                                        >
                                            <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                                <div>{rule.name}</div>
                                                {rule.description && (
                                                    <div className="text-xs font-normal text-gray-500">
                                                        {rule.description}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                                {rule.transaction_type?.label ??
                                                    '-'}
                                            </td>
                                            <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                                {rule.currency_code} {minAmount}
                                            </td>
                                            <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                                {rule.stages?.length ?? 0} Tahap
                                            </td>
                                            <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                                {rule.pending_mappings_count >
                                                0 ? (
                                                    <span className="font-semibold text-amber-600 dark:text-amber-400">
                                                        {
                                                            rule.pending_mappings_count
                                                        }{' '}
                                                        draft
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400">
                                                        0
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-6 py-4">
                                                {rule.is_active ? (
                                                    <span className="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                        Aktif
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                        Nonaktif
                                                    </span>
                                                )}
                                            </td>
                                            <td className="space-x-2 px-6 py-4 text-right">
                                                <Link
                                                    href={`/approval/rules/${rule.id}/edit`}
                                                    className="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                                >
                                                    Ubah
                                                </Link>
                                                <Link
                                                    href={`/approval/rules/${rule.id}/logs`}
                                                    className="text-xs font-medium text-gray-600 hover:text-gray-900 dark:text-gray-400"
                                                >
                                                    Log
                                                </Link>
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(
                                                            rule.id,
                                                            rule.name,
                                                        )
                                                    }
                                                    className="text-xs font-medium text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                                >
                                                    Hapus
                                                </button>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}
