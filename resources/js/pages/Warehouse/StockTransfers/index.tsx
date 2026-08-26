import React from 'react';
import { Head, Link } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';
import PageHeader from '@/components/ui/page-header';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import type { PaginatedData, StockTransfer } from './types';

type Props = {
    stockTransfers: PaginatedData<StockTransfer>;
    filters: Record<string, any>;
};

export default function StockTransferIndex({ stockTransfers }: Props) {
    const columns: DataTableColumn<StockTransfer>[] = [
        {
            key: 'id',
            header: 'ID Transfer',
            render: (st) => (
                <span className="font-mono text-xs font-semibold text-slate-900">
                    #TRF-{st.id}
                </span>
            ),
        },
        {
            key: 'from_warehouse',
            header: 'Gudang Asal',
            render: (st) => st.from_warehouse?.name ?? '-',
        },
        {
            key: 'to_warehouse',
            header: 'Gudang Tujuan',
            render: (st) => st.to_warehouse?.name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (st) => {
                const badgeStyles: Record<string, string> = {
                    draft: 'bg-slate-100 text-slate-700 ring-slate-600/20',
                    shipped: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
                    received:
                        'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
                    cancelled: 'bg-rose-50 text-rose-700 ring-rose-600/20',
                };

                return (
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                            badgeStyles[st.status] ??
                            'bg-slate-100 text-slate-700'
                        }`}
                    >
                        {st.status.toUpperCase()}
                    </span>
                );
            },
        },
        {
            key: 'shipped_at',
            header: 'Tgl Pengiriman',
            render: (st) =>
                st.shipped_at
                    ? new Date(st.shipped_at).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                      })
                    : '-',
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (st) => (
                <Link href={`/warehouse/stock-transfers/${st.id}`}>
                    <Button variant="secondary">Detail</Button>
                </Link>
            ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Transfer Stok" />

            <div className="space-y-6">
                <PageHeader
                    title="Transfer Stok Gudang"
                    description="Pengiriman dan penerimaan stok antar gudang cabang."
                />

                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <DataTable<StockTransfer>
                        columns={columns}
                        rows={stockTransfers.data}
                        getRowKey={(st) => st.id}
                        emptyMessage="Belum ada transaksi transfer stok."
                    />
                </div>
            </div>
        </CompanyLayout>
    );
}
