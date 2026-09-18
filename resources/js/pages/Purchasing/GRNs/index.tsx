import { Button } from '@heroui/react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import PurchasingFilterBar from '@/components/purchasing/purchasing-filter-bar';
import type { StatusOption } from '@/components/purchasing/purchasing-filter-bar';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import PurchasingSummaryCards from '@/components/purchasing/purchasing-summary-cards';
import type { PurchasingSummary } from '@/components/purchasing/purchasing-summary-cards';
import HerouiDataTable from '@/components/tables/heroui-data-table';
import type { DataTableColumn } from '@/components/tables/heroui-data-table';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatDate } from '@/lib/format';

type GoodsReceipt = {
    id: number;
    number: string;
    supplier_do_no: string;
    po_no?: string | null;
    purchase_order_id: number;
    status: string;
    receipt_date: string;
    created_at: string;
    purchase_order?: {
        number: string;
    } | null;
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

const GRN_STATUS_OPTIONS: StatusOption[] = [
    { value: 'draft', label: 'GRN (Draft)' },
    { value: 'submitted', label: 'GRN (Menunggu HO)' },
    { value: 'approved', label: 'GRN (Disetujui HO)' },
    { value: 'rejected', label: 'GRN (Ditolak HO)' },
];

export default function GoodsReceiptsIndex({
    goodsReceipts,
    filters,
    summary,
}: Props) {
    const { props } = usePage();
    const auth = (props.auth ?? {}) as { is_hq?: boolean };
    const isHq = auth.is_hq ?? false;

    const columns: DataTableColumn<GoodsReceipt>[] = [
        {
            key: 'created_at',
            header: 'Tanggal',
            render: (row) => formatDate(row.created_at ?? row.receipt_date),
        },
        {
            key: 'number',
            header: 'No. GRN',
            copyableKey: (row) => row.number,
            render: (row) => (
                <Link
                    href={`/purchasing/grns/${row.id}`}
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
            key: 'purchase_order_id',
            header: 'No. PO',
            render: (row) => row.po_no ?? row.purchase_order?.number ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (row) => <PurchasingStatusBadge status={row.status} />,
        },
    ];

    return (
        <CompanyLayout>
            <Head title="GRN - Penerimaan Barang" />
            <div className="w-full space-y-6">
                <PageHeader
                    title="GRN — Penerimaan Barang"
                    description="Fetch DO supplier, verifikasi scan barcode, kirim ke HO untuk approval, lalu faktur."
                    actions={
                        !isHq && (
                            <Link href="/purchasing/grns/create">
                                <Button type="button" variant="primary">
                                    Fetch DO
                                </Button>
                            </Link>
                        )
                    }
                />
                {isHq && summary && <PurchasingSummaryCards {...summary} />}
                <PurchasingFilterBar
                    initialFilters={filters}
                    statusOptions={GRN_STATUS_OPTIONS}
                    searchPlaceholder="No. GRN / No. DO…"
                />
                <HerouiDataTable<GoodsReceipt>
                    columns={columns}
                    rows={goodsReceipts.data}
                    getRowKey={(row) => row.id}
                    emptyMessage="Belum ada GRN. Klik Fetch DO untuk mulai."
                    pagination={{
                        currentPage: goodsReceipts.current_page,
                        lastPage: goodsReceipts.last_page,
                        perPage: goodsReceipts.per_page,
                        total: goodsReceipts.total,
                    }}
                    onPageChange={(page) => {
                        const params: Record<string, string> = {
                            ...(filters.search
                                ? { search: filters.search }
                                : {}),
                            ...(filters.status
                                ? { status: filters.status }
                                : {}),
                            page: String(page),
                        };
                        router.get(window.location.pathname, params, {
                            preserveState: true,
                            preserveScroll: true,
                        });
                    }}
                />
            </div>
        </CompanyLayout>
    );
}
