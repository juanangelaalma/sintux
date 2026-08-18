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
    qty_requested: number;
};

type PurchaseRequest = {
    id: number;
    number: string;
    branch_id: number;
    supplier_name?: string;
    status: string;
    request_date: string;
    expected_date?: string;
    note?: string;
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
    purchaseRequests: Paginated<PurchaseRequest>;
    filters: {
        search?: string;
        status?: string;
    };
};

export default function PurchaseRequestsIndex({ purchaseRequests }: Props) {
    const renderStatusBadge = (status: string) => {
        switch (status) {
            case 'pending':
                return (
                    <span className="inline-flex items-center rounded-full bg-amber-100/80 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                        Pending
                    </span>
                );
            case 'approved':
                return (
                    <span className="inline-flex items-center rounded-full bg-emerald-100/80 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                        Approved
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

    const columns: DataTableColumn<PurchaseRequest>[] = [
        {
            key: 'request_date',
            header: 'Date ↕',
            render: (row) =>
                row.request_date
                    ? new Date(row.request_date).toLocaleDateString('id-ID', {
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
                <Link
                    href={`/purchasing/requests/${row.id}`}
                    className="font-semibold text-indigo-600 hover:text-indigo-800 hover:underline"
                >
                    Purchase Request #{row.number}
                </Link>
            ),
        },
        {
            key: 'items',
            header: 'Items ↕',
            render: (row) => `${row.items?.length ?? 0} item`,
        },
        {
            key: 'status',
            header: 'Status ↕',
            render: (row) => renderStatusBadge(row.status),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Purchases - Requests" />
            <div className="space-y-6">
                <PageHeader title="Purchases" actions={<PurchasingHeaderDropdown />} />

                <PurchasingSummaryCards />

                <PurchasingTabs activeTab="requests" />

                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="w-48">
                        <select className="block w-full rounded-lg border-slate-300 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">All status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
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
                    <DataTable<PurchaseRequest>
                        columns={columns}
                        rows={purchaseRequests.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada data Purchase Request."
                    />
                </div>
            </div>
        </CompanyLayout>
    );
}