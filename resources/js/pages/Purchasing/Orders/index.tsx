import { Head, Link } from '@inertiajs/react';
import { PurchasingHeaderDropdown } from '@/components/purchasing/purchasing-header-dropdown';
import { PurchasingSummaryCards } from '@/components/purchasing/purchasing-summary-cards';
import { PurchasingTabs } from '@/components/purchasing/purchasing-tabs';
import type { DataTableColumn } from '@/components/tables/data-table';
import DataTable from '@/components/tables/data-table';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    qty_ordered: number;
    qty_received: number;
    unit_price: number;
    line_total: number;
};

type PurchaseOrder = {
    id: number;
    number: string;
    branch_id: number;
    supplier_id: number;
    supplier_name?: string;
    status: string;
    order_date: string;
    expected_date?: string;
    note?: string;
    total: number;
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
    purchaseOrders: Paginated<PurchaseOrder>;
    filters: {
        search?: string;
        status?: string;
    };
};

export default function PurchaseOrdersIndex({ purchaseOrders }: Props) {
    const renderStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
            case 'open':
                return (
                    <span className="inline-flex items-center rounded-full bg-amber-100/80 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                        Open
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center rounded-full bg-blue-100/80 px-2.5 py-0.5 text-xs font-semibold text-blue-800">
                        Approved
                    </span>
                );
            case 'sent':
                return (
                    <span className="inline-flex items-center rounded-full bg-sky-100/80 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                        Sent
                    </span>
                );
            case 'received':
                return (
                    <span className="inline-flex items-center rounded-full bg-emerald-100/80 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                        Received
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-700">
                        {status.toUpperCase()}
                    </span>
                );
        }
    };

    const columns: DataTableColumn<PurchaseOrder>[] = [
        {
            key: 'order_date',
            header: 'Date ↕',
            render: (row) =>
                row.order_date
                    ? new Date(row.order_date).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric',
                      })
                    : '-',
        },
        {
            key: 'number',
            header: 'Number ↕',
            render: (row) => (
                <div>
                    <Link
                        href={`/purchasing/orders/${row.id}`}
                        className="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                    >
                        Purchase Order #{row.number}
                    </Link>
                    {row.note && <div className="text-xs text-slate-500 max-w-xs truncate">{row.note}</div>}
                </div>
            ),
        },
        {
            key: 'supplier_id',
            header: 'Vendor ↕',
            render: (row) => <span className="font-medium text-indigo-600">{row.supplier_name || 'PT. Jurnal Consulting'}</span>,
        },
        {
            key: 'expected_date',
            header: 'Due date ↕',
            render: (row) =>
                row.expected_date
                    ? new Date(row.expected_date).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: '2-digit',
                          year: 'numeric',
                      })
                    : '-',
        },
        {
            key: 'status',
            header: 'Status ↕',
            render: (row) => renderStatusBadge(row.status),
        },
        {
            key: 'deposit',
            header: 'Deposit ↕',
            render: () => 'Rp. 0,00',
        },
        {
            key: 'total',
            header: 'Total ↕',
            render: (row) => `Rp. ${Number(row.total || 0).toLocaleString('id-ID', { minimumFractionDigits: 2 })}`,
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Purchases - Orders" />
            <div className="space-y-6">
                <PageHeader title="Purchases" actions={<PurchasingHeaderDropdown />} />

                <PurchasingSummaryCards />

                <PurchasingTabs activeTab="orders" />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="w-48">
                        <select className="block w-full rounded-lg border-slate-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All status</option>
                            <option value="open">Open</option>
                            <option value="approved">Approved</option>
                            <option value="sent">Sent</option>
                            <option value="received">Received</option>
                        </select>
                    </div>

                    <div className="flex items-center gap-2">
                        <div className="relative w-64">
                            <input
                                type="text"
                                placeholder="Search transaction"
                                className="w-full rounded-lg border-slate-300 py-2 pl-9 pr-4 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                            <svg
                                className="absolute left-3 top-2.5 size-4 text-slate-400"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                />
                            </svg>
                        </div>
                        <button
                            type="button"
                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            <svg className="size-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                                />
                            </svg>
                            <span>Filter</span>
                        </button>
                    </div>
                </div>

                <div className="rounded-xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <DataTable<PurchaseOrder>
                        columns={columns}
                        rows={purchaseOrders.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada data Purchase Order."
                    />
                </div>
            </div>
        </CompanyLayout>
    );
}