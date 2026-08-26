import { Head, Link, router } from '@inertiajs/react';
import React, { useState } from 'react';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';

type PendingApproval = {
    id: number;
    transaction_type: string;
    transaction_type_label: string;
    transaction_id: number;
    document_number?: string | null;
    creator_id: number;
    creator_name?: string | null;
    total: number;
    currency_code: string;
    rule_name: string;
    current_stage_order: number;
    approval_type: 'any' | 'all';
    mapped_at?: string | null;
};

type Props = {
    pendingApprovals: PendingApproval[];
};

export default function Inbox({ pendingApprovals }: Props) {
    const [comment, setComment] = useState('');
    const [processing, setProcessing] = useState(false);

    const handleAction = (mappingId: number, action: 'approve' | 'reject') => {
        setProcessing(true);
        router.post(
            `/approval/mappings/${mappingId}/${action}`,
            { comment },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setComment('');
                },
            },
        );
    };

    const getDocumentUrl = (item: PendingApproval) => {
        switch (item.transaction_type) {
            case 'purchase_request':
                return `/purchasing/requests/${item.transaction_id}`;
            case 'purchase_order':
                return `/purchasing/orders/${item.transaction_id}`;
            case 'purchase_invoice':
                return `/purchasing/invoices/${item.transaction_id}`;
            default:
                return '#';
        }
    };

    return (
        <CompanyLayout>
            <Head title="Approval Inbox" />

            <div className="space-y-6">
                <PageHeader
                    title="Approval Inbox"
                    description="Daftar transaksi yang menunggu persetujuan Anda."
                />

                {pendingApprovals.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-gray-300 bg-white p-12 text-center shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-emerald-50 dark:bg-emerald-950/40">
                            <span className="text-xl">✅</span>
                        </div>
                        <h3 className="mt-4 text-base font-semibold text-gray-900 dark:text-white">
                            Tidak Ada Transaksi Menunggu
                        </h3>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Seluruh transaksi yang memerlukan persetujuan Anda
                            telah diproses.
                        </p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs dark:border-gray-800 dark:bg-gray-900">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-gray-200 bg-gray-50/50 text-xs font-semibold tracking-wider text-gray-500 uppercase dark:border-gray-800 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th className="px-6 py-3.5">Dokumen</th>
                                    <th className="px-6 py-3.5">
                                        Tipe Transaksi
                                    </th>
                                    <th className="px-6 py-3.5">Pembuat</th>
                                    <th className="px-6 py-3.5">Nominal</th>
                                    <th className="px-6 py-3.5">Tahap</th>
                                    <th className="px-6 py-3.5 text-right">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                                {pendingApprovals.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="hover:bg-gray-50/50 dark:hover:bg-gray-800/50"
                                    >
                                        <td className="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                            <Link
                                                href={getDocumentUrl(item)}
                                                className="font-semibold text-brand-600 hover:underline dark:text-brand-400"
                                            >
                                                {item.document_number ??
                                                    `#${item.transaction_id}`}
                                            </Link>
                                            <div className="text-xs font-normal text-gray-500">
                                                Aturan: {item.rule_name}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                            {item.transaction_type_label}
                                        </td>
                                        <td className="px-6 py-4 text-gray-700 dark:text-gray-300">
                                            {item.creator_name ??
                                                `User #${item.creator_id}`}
                                        </td>
                                        <td className="px-6 py-4 font-semibold text-gray-900 dark:text-white">
                                            {item.currency_code}{' '}
                                            {item.total.toLocaleString('id-ID')}
                                        </td>
                                        <td className="px-6 py-4">
                                            <span className="inline-flex rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                                Tahap {item.current_stage_order}{' '}
                                                (
                                                {item.approval_type === 'any'
                                                    ? 'Salah Satu'
                                                    : 'Semua'}
                                                )
                                            </span>
                                        </td>
                                        <td className="space-x-2 px-6 py-4 text-right">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleAction(
                                                        item.id,
                                                        'approve',
                                                    )
                                                }
                                                disabled={processing}
                                                className="rounded bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                Setujui
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleAction(
                                                        item.id,
                                                        'reject',
                                                    )
                                                }
                                                disabled={processing}
                                                className="rounded bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-700 disabled:opacity-50"
                                            >
                                                Tolak
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </CompanyLayout>
    );
}
