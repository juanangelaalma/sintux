import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatCurrency, formatDate } from '@/lib/format';

type PurchaseQuote = {
    id: number;
    number: string;
    supplier_id: number;
    supplier_name?: string;
    status: string;
    quote_date: string;
    valid_until?: string;
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
    purchaseQuotes: Paginated<PurchaseQuote>;
    filters: {
        search?: string;
        status?: string;
    };
    summary?: PurchasingSummary;
};

export default function PurchaseQuotesIndex({
    purchaseQuotes,
    filters,
    summary,
}: Props) {
    const columns: DataTableColumn<PurchaseQuote>[] = [
        {
            key: 'quote_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.quote_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/quotes/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Purchase Quote #{row.number}
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
            key: 'valid_until',
            header: 'Berlaku hingga',
            render: (row) => formatDate(row.valid_until),
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
        <PurchasingListPage<PurchaseQuote>
            headTitle="Pembelian - Penawaran Harga"
            activeTab="quotes"
            columns={columns}
            rows={purchaseQuotes.data}
            getRowKey={(row) => row.id}
            emptyMessage="Belum ada data Penawaran Harga."
            filters={filters}
            pagination={{
                currentPage: purchaseQuotes.current_page,
                lastPage: purchaseQuotes.last_page,
                perPage: purchaseQuotes.per_page,
                total: purchaseQuotes.total,
            }}
            summary={summary}
        />
    );
}
