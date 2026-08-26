import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatDate } from '@/lib/format';

type GoodsReceipt = {
    id: number;
    number: string;
    purchase_order_id: number;
    warehouse_id: number;
    status: string;
    receipt_date: string;
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
    goodsReceipts: Paginated<GoodsReceipt>;
    filters: {
        search?: string;
        status?: string;
    };
    summary?: PurchasingSummary;
};

export default function GoodsReceiptsIndex({
    goodsReceipts,
    filters,
    summary,
}: Props) {
    const columns: DataTableColumn<GoodsReceipt>[] = [
        {
            key: 'receipt_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.receipt_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/grns/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Goods Receipt #{row.number}
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
            key: 'purchase_order_id',
            header: 'No. PO Ref',
            render: (row) => `#${row.purchase_order_id}`,
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
    ];

    return (
        <PurchasingListPage<GoodsReceipt>
            headTitle="Pembelian - Penerimaan Barang"
            activeTab="grns"
            columns={columns}
            rows={goodsReceipts.data}
            getRowKey={(row) => row.id}
            emptyMessage="Belum ada data Penerimaan Barang."
            filters={filters}
            pagination={{
                currentPage: goodsReceipts.current_page,
                lastPage: goodsReceipts.last_page,
                perPage: goodsReceipts.per_page,
                total: goodsReceipts.total,
            }}
            summary={summary}
        />
    );
}
