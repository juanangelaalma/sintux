import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import SettingsLayout from '@/layouts/settings/layout';
import { formatRate } from './types';
import type { TaxRow } from './types';

type Props = {
    taxes: TaxRow[];
};

function typeBadge(tax: TaxRow) {
    if (tax.type === 'group') {
        return (
            <span className="inline-flex rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300">
                Grup
            </span>
        );
    }

    if (tax.is_withholding) {
        return (
            <span className="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                Pemotongan
            </span>
        );
    }

    return (
        <span className="inline-flex rounded-full bg-sky-50 px-2 py-0.5 text-xs font-semibold text-sky-700 dark:bg-sky-950/40 dark:text-sky-300">
            Satuan
        </span>
    );
}

export default function Index({ taxes }: Props) {
    const [query, setQuery] = useState('');

    const rows = useMemo(() => {
        const q = query.trim().toLocaleLowerCase('id');

        if (!q) {
            return taxes;
        }

        return taxes.filter(
            (tax) =>
                tax.name.toLocaleLowerCase('id').includes(q) ||
                tax.code.toLocaleLowerCase('id').includes(q),
        );
    }, [taxes, query]);

    const handleDelete = (tax: TaxRow) => {
        if (tax.in_use) {
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus pajak "${tax.name}"?`)) {
            router.delete(`/accounting/taxes/${tax.id}`);
        }
    };

    return (
        <SettingsLayout>
            <Head title="Pajak" />

            <div className="space-y-6">
                <PageHeader
                    title="Pajak"
                    description="Kelola pajak perusahaan: tarif, pemetaan akun, dan proteksi pemakaian dokumen."
                    actions={
                        <Link href="/accounting/taxes/create">
                            <Button variant="primary">+ Buat Pajak Baru</Button>
                        </Link>
                    }
                />

                <div className="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-end dark:border-gray-800 dark:bg-gray-900">
                    <form
                        onSubmit={(e) => e.preventDefault()}
                        className="flex items-center gap-2"
                    >
                        <input
                            type="search"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari nama atau kode..."
                            aria-label="Cari pajak berdasarkan nama atau kode"
                            className="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                        />
                    </form>
                </div>

                {rows.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <h3 className="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                            {taxes.length === 0
                                ? 'Belum ada pajak'
                                : 'Tidak ada pajak yang cocok'}
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {taxes.length === 0
                                ? 'Pajak yang Anda buat akan muncul di sini.'
                                : 'Coba kata kunci lain.'}
                        </p>
                        {taxes.length === 0 && (
                            <div className="mt-6">
                                <Link href="/accounting/taxes/create">
                                    <Button variant="primary">
                                        + Buat Pajak Baru
                                    </Button>
                                </Link>
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <table className="w-full min-w-[900px] text-left text-sm">
                            <thead className="border-b border-gray-200 bg-gray-50/50 text-xs text-gray-500 uppercase dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Nama
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Persentase Efektif
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Akun Penjualan
                                    </th>
                                    <th className="px-6 py-3.5 font-semibold">
                                        Akun Pembelian
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
                                {rows.map((tax) => (
                                    <tr
                                        key={tax.id}
                                        className="hover:bg-gray-50/50 dark:hover:bg-gray-800/50"
                                    >
                                        <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span>{tax.name}</span>
                                                {typeBadge(tax)}
                                                {tax.dpp_multiplier && (
                                                    <span className="inline-flex rounded-full bg-violet-50 px-2 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-950/40 dark:text-violet-300">
                                                        Pengali 11/12
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-0.5 text-xs font-normal text-gray-500">
                                                {tax.code}
                                                {tax.type === 'group' &&
                                                    tax.members.length > 0 &&
                                                    ` · ${tax.members.length} anggota`}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-gray-700 tabular-nums dark:text-gray-300">
                                            {formatRate(tax.rate)}
                                        </td>
                                        <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                            {tax.sales_account?.name ?? '—'}
                                        </td>
                                        <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                            {tax.purchase_account?.name ?? '—'}
                                        </td>
                                        <td className="px-6 py-4">
                                            {tax.is_active ? (
                                                <span className="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                                    Aktif
                                                </span>
                                            ) : (
                                                <span className="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                                    Nonaktif
                                                </span>
                                            )}
                                        </td>
                                        <td className="space-x-3 px-6 py-4 text-right whitespace-nowrap">
                                            <Link
                                                href={`/accounting/taxes/${tax.id}`}
                                                className="text-xs font-medium text-brand-600 hover:text-brand-700 dark:text-brand-400"
                                            >
                                                Detail
                                            </Link>
                                            <span
                                                title={
                                                    tax.in_use
                                                        ? 'Pajak sedang terpakai dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.'
                                                        : undefined
                                                }
                                                className="inline-block"
                                            >
                                                <button
                                                    type="button"
                                                    disabled={tax.in_use}
                                                    onClick={() =>
                                                        handleDelete(tax)
                                                    }
                                                    className="text-xs font-medium text-rose-600 hover:text-rose-700 disabled:cursor-not-allowed disabled:opacity-40 dark:text-rose-400"
                                                >
                                                    Hapus
                                                </button>
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </SettingsLayout>
    );
}
