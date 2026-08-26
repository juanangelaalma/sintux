import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatCurrency, formatDate } from '@/lib/format';

type JoinPurchaseInvoice = {
    id: number;
    number: string;
    status: string;
    join_date: string;
    total_amount: number;
    supplier_name?: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    joinPurchaseInvoices: Paginated<JoinPurchaseInvoice>;
    filters: {
        search?: string;
        status?: string;
    };
    summary?: PurchasingSummary;
};

export default function JoinPurchaseInvoicesIndex({
    joinPurchaseInvoices,
    filters,
    summary,
}: Props) {
    const columns: DataTableColumn<JoinPurchaseInvoice>[] = [
        {
            key: 'join_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.join_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/joins/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Join Invoice #{row.number}
                </Link>
            ),
        },
        {
            key: 'supplier_name',
            header: 'Supplier',
            render: (row) => (
                <span className="font-medium text-accent">
                    {row.supplier_name || 'PT. Behaestex'}
                </span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
        {
            key: 'total_amount',
            header: 'Total',
            align: 'right',
            render: (row) => formatCurrency(row.total_amount || 0),
        },
    ];

    return (
        <PurchasingListPage<JoinPurchaseInvoice>
            headTitle="Pembelian - Tukar Faktur"
            activeTab="joins"
            columns={columns}
            rows={joinPurchaseInvoices.data}
            getRowKey={(row) => row.id}
            emptyMessage="Belum ada data Tukar Faktur."
            filters={filters}
            pagination={{
                currentPage: joinPurchaseInvoices.current_page,
                lastPage: joinPurchaseInvoices.last_page,
                perPage: joinPurchaseInvoices.per_page,
                total: joinPurchaseInvoices.total,
            }}
            summary={summary}
        />
    );
}
