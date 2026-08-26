import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatCurrency, formatDate } from '@/lib/format';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    qty: number;
    unit_price: number;
    line_total: number;
};

type PurchaseInvoice = {
    id: number;
    number: string;
    branch_id: number;
    supplier_id: number;
    supplier_name?: string;
    purchase_order_id?: number;
    status: string;
    invoice_date: string;
    due_date?: string;
    total: number;
    subtotal: number;
    items?: Item[];
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    purchaseInvoices: Paginated<PurchaseInvoice>;
    filters: {
        search?: string;
        status?: string;
    };
    summary?: PurchasingSummary;
};

export default function PurchaseInvoicesIndex({
    purchaseInvoices,
    filters,
    summary,
}: Props) {
    const columns: DataTableColumn<PurchaseInvoice>[] = [
        {
            key: 'invoice_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.invoice_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/invoices/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Purchase Invoice #{row.number}
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
            key: 'due_date',
            header: 'Tgl. jatuh tempo',
            render: (row) => formatDate(row.due_date),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
        {
            key: 'subtotal',
            header: 'Sisa tagihan',
            align: 'right',
            render: (row) => formatCurrency(row.total || 0),
        },
        {
            key: 'total',
            header: 'Total',
            align: 'right',
            render: (row) => formatCurrency(row.total || 0),
        },
    ];

    return (
        <PurchasingListPage<PurchaseInvoice>
            headTitle="Pembelian - Faktur Pembelian"
            activeTab="invoices"
            columns={columns}
            rows={purchaseInvoices.data}
            getRowKey={(row) => row.id}
            emptyMessage="Belum ada data Faktur Pembelian."
            filters={filters}
            pagination={{
                currentPage: purchaseInvoices.current_page,
                lastPage: purchaseInvoices.last_page,
                perPage: purchaseInvoices.per_page,
                total: purchaseInvoices.total,
            }}
            summary={summary}
        />
    );
}
