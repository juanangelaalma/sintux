import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatDate } from '@/lib/format';

type GoodsReceipt = {
    id: number;
    number: string;
    supplier_do_no: string;
    po_no?: string | null;
    status: string;
    created_at: string;
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
    pendingGrnCount?: number;
};

export default function GrnInboxIndex({
    goodsReceipts,
    filters,
    summary,
    pendingGrnCount = 0,
}: Props) {
    const columns: DataTableColumn<GoodsReceipt>[] = [
        {
            key: 'created_at',
            header: 'Tanggal',
            render: (row) => formatDate(row.created_at),
        },
        {
            key: 'number',
            header: 'No.',
            render: (row) => (
                <Link
                    href={`/purchasing/grn-inbox/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    {row.number}
                </Link>
            ),
        },
        {
            key: 'supplier_do_no',
            header: 'No. DO',
            render: (row) => (
                <span className="font-mono text-xs">{row.supplier_do_no}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
    ];

    return (
        <PurchasingListPage<GoodsReceipt>
            headTitle="Pembelian - Approval GRN"
            activeTab="grns"
            pendingGrnCount={pendingGrnCount}
            columns={columns}
            rows={goodsReceipts.data}
            getRowKey={(row) => row.id}
            emptyMessage="Tidak ada GRN menunggu approval."
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
