import { Head, Link, router } from '@inertiajs/react';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import SettingsLayout from '@/layouts/settings/layout';
import { formatRate } from './types';
import type { TaxRow } from './types';

type Props = {
    tax: TaxRow;
};

function Definition({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-xs font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400">
                {label}
            </dt>
            <dd className="mt-1 text-sm text-gray-900 dark:text-white">
                {children}
            </dd>
        </div>
    );
}

export default function Show({ tax }: Props) {
    const handleDelete = () => {
        if (tax.in_use) {
            return;
        }

        if (confirm(`Apakah Anda yakin ingin menghapus pajak "${tax.name}"?`)) {
            router.delete(`/accounting/taxes/${tax.id}`);
        }
    };

    return (
        <SettingsLayout>
            <Head title={`Pajak — ${tax.name}`} />

            <div className="max-w-3xl space-y-6">
                <PageHeader
                    title={tax.name}
                    description={`${tax.code} · ${tax.type === 'group' ? 'Pajak grup' : tax.is_withholding ? 'Pajak pemotongan' : 'Pajak satuan'}`}
                    actions={
                        <div className="flex gap-2">
                            <Link href={`/accounting/taxes/${tax.id}/edit`}>
                                <Button variant="secondary">Ubah</Button>
                            </Link>
                            <span
                                title={
                                    tax.in_use
                                        ? 'Pajak sedang terpakai dan tidak dapat dihapus. Nonaktifkan sebagai gantinya.'
                                        : undefined
                                }
                                className="inline-block"
                            >
                                <Button
                                    variant="danger"
                                    disabled={tax.in_use}
                                    onClick={handleDelete}
                                >
                                    Hapus
                                </Button>
                            </span>
                        </div>
                    }
                />

                {tax.in_use && (
                    <div className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-300">
                        Pajak ini sedang terpakai — hanya nama dan status yang
                        dapat diubah. Untuk grup, daftar anggota, urutan, dan
                        majemuk ikut terkunci.
                    </div>
                )}

                <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                    <dl className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <Definition label="Nama">{tax.name}</Definition>
                        <Definition label="Kode">{tax.code}</Definition>
                        <Definition label="Persentase Efektif">
                            <span className="tabular-nums">
                                {formatRate(tax.rate)}
                            </span>
                        </Definition>
                        <Definition label="Status">
                            {tax.is_active ? (
                                <span className="inline-flex rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                    Aktif
                                </span>
                            ) : (
                                <span className="inline-flex rounded-full bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                    Nonaktif
                                </span>
                            )}
                        </Definition>
                        {tax.type === 'single' && (
                            <>
                                <Definition label="Pengali 11/12">
                                    {tax.dpp_multiplier ? 'Ya' : 'Tidak'}
                                </Definition>
                                <Definition label="Pemotongan">
                                    {tax.is_withholding ? 'Ya' : 'Tidak'}
                                </Definition>
                                <Definition label="Akun Pajak Penjualan">
                                    {tax.sales_account?.name ?? '—'}
                                </Definition>
                                <Definition label="Akun Pajak Pembelian">
                                    {tax.purchase_account?.name ?? '—'}
                                </Definition>
                            </>
                        )}
                    </dl>
                </section>

                {tax.type === 'group' && (
                    <section className="rounded-xl border border-gray-200 bg-white p-6 shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <h2 className="text-sm font-semibold text-gray-900 dark:text-white">
                            Anggota grup ({tax.members.length})
                        </h2>
                        {tax.members.length === 0 ? (
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Grup ini belum memiliki anggota.
                            </p>
                        ) : (
                            <div className="mt-3 overflow-x-auto">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-gray-200 text-xs text-gray-500 uppercase dark:border-gray-800 dark:text-gray-400">
                                        <tr>
                                            <th className="py-2 pr-4 font-semibold">
                                                #
                                            </th>
                                            <th className="py-2 pr-4 font-semibold">
                                                Nama
                                            </th>
                                            <th className="py-2 pr-4 font-semibold">
                                                Tarif
                                            </th>
                                            <th className="py-2 font-semibold">
                                                Majemuk
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                        {tax.members.map((member, i) => (
                                            <tr key={member.id ?? i}>
                                                <td className="py-2 pr-4 text-gray-500 tabular-nums">
                                                    {member.position}
                                                </td>
                                                <td className="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                                                    {member.name ?? '—'}
                                                </td>
                                                <td className="py-2 pr-4 text-gray-700 tabular-nums dark:text-gray-300">
                                                    {formatRate(
                                                        member.signed_rate,
                                                    )}
                                                </td>
                                                <td className="py-2 text-gray-700 dark:text-gray-300">
                                                    {member.is_compound
                                                        ? 'Ya'
                                                        : 'Tidak'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                )}

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
