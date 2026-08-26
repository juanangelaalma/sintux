import { Spinner } from '@heroui/react';
import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import HerouiDataTable from '@/components/tables/heroui-data-table';
import type {
    DataTableColumn,
    DataTablePagination,
} from '@/components/tables/heroui-data-table';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import PurchasingFilterBar from './purchasing-filter-bar';
import type { PurchasingFilter, StatusOption } from './purchasing-filter-bar';
import PurchasingHeaderDropdown from './purchasing-header-dropdown';
import PurchasingSummaryCards from './purchasing-summary-cards';
import type { PurchasingSummary } from './purchasing-summary-cards';
import PurchasingTabs from './purchasing-tabs';
import type { TabKey } from './purchasing-tabs';

type PurchasingListPageProps<T> = {
    headTitle: string;
    activeTab: TabKey;
    columns: DataTableColumn<T>[];
    rows: T[];
    getRowKey: (row: T) => string | number;
    emptyMessage: string;
    filters: PurchasingFilter;
    statusOptions?: StatusOption[];
    pagination: DataTablePagination;
    summary?: PurchasingSummary;
};

export default function PurchasingListPage<T>({
    headTitle,
    activeTab,
    columns,
    rows,
    getRowKey,
    emptyMessage,
    filters,
    statusOptions,
    pagination,
    summary,
}: PurchasingListPageProps<T>) {
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        const removeStart = router.on('start', () => {
            setIsLoading(true);
        });

        const removeFinish = router.on('finish', () => {
            setIsLoading(false);
        });

        return () => {
            removeStart();
            removeFinish();
        };
    }, []);

    return (
        <CompanyLayout>
            <Head title={headTitle} />
            <div className="w-full space-y-6">
                {/* Header Section + Breadcrumbs */}
                <div>
                    <p className="text-xs font-semibold tracking-widest text-muted uppercase">
                        HOME &gt; PEMBELIAN
                    </p>
                    <PageHeader
                        title="Pembelian"
                        actions={<PurchasingHeaderDropdown />}
                    />
                </div>

                {/* Persistent KPI Summary Cards */}
                {summary && <PurchasingSummaryCards {...summary} />}

                {/* Sub-module Navigation Tabs */}
                <PurchasingTabs activeTab={activeTab} />

                {/* Status Filter & Export Bar */}
                <PurchasingFilterBar
                    initialFilters={filters}
                    statusOptions={statusOptions}
                />

                {/* Data Table Section with Smooth Loading State */}
                {isLoading ? (
                    <div className="flex min-h-[320px] w-full flex-col items-center justify-center gap-3 rounded-xl border border-border bg-surface py-16 text-center shadow-2xs transition-all">
                        <Spinner size="lg" />
                        <span className="text-sm font-medium text-muted">
                            Memuat transaksi
                        </span>
                    </div>
                ) : (
                    <HerouiDataTable<T>
                        columns={columns}
                        rows={rows}
                        getRowKey={getRowKey}
                        emptyMessage={emptyMessage}
                        pagination={pagination}
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
                )}
            </div>
        </CompanyLayout>
    );
}
