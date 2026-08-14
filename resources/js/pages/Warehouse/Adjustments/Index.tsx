import { Head, Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import type { StockAdjustment } from './types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    adjustments: Paginated<StockAdjustment>;
    filters: {
        search?: string;
        type?: string;
        status?: string;
        warehouse_id?: number;
    };
    warehouses: Array<{ id: number; name: string }>;
};

export default function AdjustmentsIndex({ adjustments }: Props) {
    const changePage = (page: number) => {
        router.get(
            '/warehouse/adjustments',
            { page },
            { preserveState: true, preserveScroll: true }
        );
    };

    const renderTypeBadge = (type: string) => {
        if (type === 'in') {
            return (
                <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20">
                    STOK MASUK (IN)
                </span>
            );
        }
        return (
            <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">
                STOK KELUAR (OUT)
            </span>
        );
    };

    const renderStatusBadge = (status: string) => {
        if (status === 'posted') {
            return (
                <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                    POSTED
                </span>
            );
        }
        return (
            <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                DRAFT
            </span>
        );
    };

    const columns: DataTableColumn<StockAdjustment>[] = [
        {
            key: 'adjustment_number',
            header: 'No. Penyesuaian',
            render: (row) => (
                <span className="font-mono text-sm font-semibold text-slate-900">
                    {row.adjustment_number}
                </span>
            ),
        },
        {
            key: 'type',
            header: 'Tipe',
            render: (row) => renderTypeBadge(row.type),
        },
        {
            key: 'warehouse',
            header: 'Gudang',
            render: (row) => row.warehouse?.name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => renderStatusBadge(row.status),
        },
        {
            key: 'created_at',
            header: 'Tanggal Dibuat',
            render: (row) =>
                row.created_at
                    ? new Date(row.created_at).toLocaleDateString('id-ID', {
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
            render: (row) => (
                <Link
                    href={`/warehouse/adjustments/${row.id}`}
                    className="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-900"
                >
                    Detail &rarr;
                </Link>
            ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Penyesuaian Stok" />

            <div className="space-y-6">
                <PageHeader
                    title="Penyesuaian Stok (Stock Adjustments)"
                    description="Kelola pencatatan penyesuaian stok masuk (inbound) dan stok keluar secara manual."
                    actions={
                        <Link href="/warehouse/adjustments/create">
                            <Button variant="primary">
                                + Buat Penyesuaian Stok
                            </Button>
                        </Link>
                    }
                />

                <DataTable
                    columns={columns}
                    rows={adjustments.data}
                    getRowKey={(row) => row.id}
                    emptyMessage="Belum ada penyesuaian stok. Klik 'Buat Penyesuaian Stok' untuk membuat."
                    pagination={{
                        currentPage: adjustments.current_page,
                        lastPage: adjustments.last_page,
                        total: adjustments.total,
                        perPage: adjustments.per_page,
                    }}
                    onPageChange={changePage}
                />
            </div>
        </CompanyLayout>
    );
}
