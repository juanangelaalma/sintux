import { Head, Link } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import type { StockRequest, StockRequestItem } from './types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    stockRequests: Paginated<StockRequest>;
    filters: {
        search?: string;
        status?: string;
    };
};

export default function StockRequestsIndex({ stockRequests }: Props) {
    const renderStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                        PENDING
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                        DISETUJUI
                    </span>
                );
            case 'partially_approved':
                return (
                    <span className="inline-flex items-center rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-medium text-sky-700 ring-1 ring-inset ring-sky-600/20">
                        DISETUJUI SEBAGIAN
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 ring-1 ring-inset ring-rose-600/20">
                        DITOLAK
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-700">
                        {status.toUpperCase()}
                    </span>
                );
        }
    };

    const columns: DataTableColumn<StockRequest>[] = [
        {
            key: 'id',
            header: 'ID',
            render: (row: StockRequest) => `#${row.id}`,
        },
        {
            key: 'requested_at',
            header: 'Tgl Pengajuan',
            render: (row: StockRequest) =>
                row.requested_at
                    ? new Date(row.requested_at).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                      })
                    : '-',
        },
        {
            key: 'requesting_warehouse',
            header: 'Gudang Peminta',
            render: (row: StockRequest) => (
                <div>
                    <div className="font-medium text-slate-900">{row.requesting_warehouse?.name ?? '-'}</div>
                    <div className="text-xs text-slate-500">
                        {row.requesting_warehouse?.branch?.name ?? ''}
                    </div>
                </div>
            ),
        },
        {
            key: 'destination_warehouse',
            header: 'Gudang Tujuan (HQ)',
            render: (row: StockRequest) => (
                <div>
                    <div className="font-medium text-slate-900">{row.destination_warehouse?.name ?? '-'}</div>
                    <div className="text-xs text-slate-500">
                        {row.destination_warehouse?.branch?.name ?? ''}
                    </div>
                </div>
            ),
        },
        {
            key: 'items',
            header: 'Item & Qty',
            render: (row: StockRequest) => {
                const itemCount = row.items?.length ?? 0;
                const totalQty =
                    row.items?.reduce(
                        (sum: number, item: StockRequestItem) => sum + Number(item.qty_requested),
                        0,
                    ) ?? 0;
                return (
                    <span className="text-sm text-slate-700">
                        {itemCount} jenis ({totalQty} total)
                    </span>
                );
            },
        },
        {
            key: 'requested_by',
            header: 'Pemohon',
            render: (row: StockRequest) => row.requested_by_user?.name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (row: StockRequest) => renderStatusBadge(row.status),
        },
        {
            key: 'actions',
            header: 'Aksi',
            render: (row: StockRequest) => (
                <Link
                    href={`/warehouse/stock-requests/${row.id}`}
                    className="inline-flex items-center font-medium text-brand-600 hover:text-brand-700 text-sm"
                >
                    {row.status === 'pending' ? 'Proses' : 'Detail'}
                </Link>
            ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Permintaan Stok" />
            <div className="space-y-6">
                <PageHeader
                    title="Permintaan Stok"
                    description="Daftar pengajuan pasokan stok dari cabang ke Gudang Utama (HQ)."
                    actions={
                        <Link href="/warehouse/stock-requests/create">
                            <Button variant="primary">Buat Permintaan</Button>
                        </Link>
                    }
                />

                <DataTable<StockRequest>
                    columns={columns}
                    rows={stockRequests.data}
                    getRowKey={(row) => row.id}
                    emptyMessage="Belum ada data permintaan stok."
                />
            </div>
        </CompanyLayout>
    );
}
