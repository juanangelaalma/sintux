import { Link } from '@inertiajs/react';
import PurchasingListPage from '@/components/purchasing/purchasing-list-page';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import { formatDate } from '@/lib/format';

type PurchaseRequest = {
    id: number;
    number: string;
    department_id?: number;
    status: string;
    request_date: string;
    required_date?: string;
    requester_name?: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    purchaseRequests: Paginated<PurchaseRequest>;
    filters: {
        search?: string;
        status?: string;
    };
    summary?: PurchasingSummary;
};

export default function PurchaseRequestsIndex({
    purchaseRequests,
    filters,
    summary,
}: Props) {
    const columns: DataTableColumn<PurchaseRequest>[] = [
        {
            key: 'request_date',
            header: 'Tanggal',
            render: (row) => formatDate(row.request_date),
        },
        {
            key: 'number',
            header: 'No.',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/requests/${row.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    Purchase Request #{row.number}
                </Link>
            ),
        },
        {
            key: 'requester_name',
            header: 'Pemohon',
            render: (row) => (
                <span className="font-medium text-accent">
                    {row.requester_name || 'Staff Purchasing'}
                </span>
            ),
        },
        {
            key: 'required_date',
            header: 'Tgl. dibutuhkan',
            render: (row) => formatDate(row.required_date),
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
    ];

    return (
        <PurchasingListPage<PurchaseRequest>
            headTitle="Pembelian - Permintaan Pembelian"
            activeTab="requests"
            columns={columns}
            rows={purchaseRequests.data}
            getRowKey={(row) => row.id}
            emptyMessage="Belum ada data Permintaan Pembelian."
            filters={filters}
            pagination={{
                currentPage: purchaseRequests.current_page,
                lastPage: purchaseRequests.last_page,
                perPage: purchaseRequests.per_page,
                total: purchaseRequests.total,
            }}
            summary={summary}
        />
    );
}
