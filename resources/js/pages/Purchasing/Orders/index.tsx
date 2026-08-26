import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatCurrency, formatDate } from '@/lib/format';

type PurchaseOrder = {
    id: number;
    number: string;
    supplier_id: number;
    supplier_name?: string;
    status: string;
    order_date: string;
    expected_date?: string;
    total: number;
    subtotal: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    purchaseOrders: Paginated<PurchaseOrder>;
    filters: {
        search?: string;
        status?: string;
    };
    summary?: PurchasingSummary;
};

export default function PurchaseOrdersIndex({
    purchaseOrders,
    filters,
    summary,
}: Props) {
    const columns: DataTableColumn<PurchaseOrder>[] = [
        {
            key: 'order_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.order_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/orders/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Purchase Order #{row.number}
                </Link>
            ),
        },
        {
            key: 'supplier_id',
            header: 'Supplier',
            render: (row) => (
                <span className="font-medium text-accent">
                    {row.supplier_name || 'PT. Behaestex'}
                </span>
            ),
        },
        {
            key: 'expected_date',
            header: 'Perkiraan tiba',
            render: (row) => formatDate(row.expected_date),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            render: (row) => formatCurrency(row.total || 0),
        },
    ];

    return (
        <PurchasingListPage<PurchaseOrder>
            headTitle="Pembelian - Pesanan Pembelian"
            activeTab="orders"
            columns={columns}
            rows={purchaseOrders.data}
            getRowKey={(row) => row.id}
            emptyMessage="Belum ada data Pesanan Pembelian."
            filters={filters}
            pagination={{
                currentPage: purchaseOrders.current_page,
                lastPage: purchaseOrders.last_page,
                perPage: purchaseOrders.per_page,
                total: purchaseOrders.total,
            }}
            summary={summary}
        />
    );
}
