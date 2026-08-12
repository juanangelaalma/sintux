import React from 'react';
import { Head, router } from '@inertiajs/react';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { ProductActionsDropdown } from './components/product-actions-dropdown';
import { ProductSummaryCards } from './components/product-summary-cards';
import { ItemsTab } from './components/tabs/items-tab';
import { MasterDataTab } from './components/tabs/master-data-tab';
import { WarehouseTab } from './components/tabs/warehouse-tab';

type ProductStats = {
    available_count: number;
    low_stock_count: number;
    out_of_stock_count: number;
    warehouses_count: number;
    pending_requests_count: number;
};

type Props = {
    stats: ProductStats;
    activeTab: string; // 'items' | 'gudang' | 'master'
    subTab: string;
    filters: Record<string, any>;
    products: any;
    categories: any[];
    brands: any[];
    uoms: any[];
    warehouses: any[];
    stockBalances: any;
    stockRequests: any;
};

export default function ProductIndex({
    stats,
    activeTab,
    subTab,
    filters,
    products,
    categories,
    brands,
    uoms,
    warehouses,
    stockBalances,
    stockRequests,
}: Props) {
    const handleTabChange = (tab: string, defaultSub: string) => {
        router.get('/product', { tab, sub: defaultSub }, { preserveState: true });
    };

    return (
        <CompanyLayout>
            <Head title="Produk" />

            <div className="space-y-6">
                {/* Header & Quick Action */}
                <PageHeader
                    title="Produk"
                    description="Manajemen katalog barang, varian, lokasi gudang, persediaan stok, dan pengajuan transfer."
                    actions={<ProductActionsDropdown />}
                />

                {/* Summary KPI Cards */}
                <ProductSummaryCards stats={stats} />

                {/* Main Level-1 Tabbed Hub Bar */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="border-b border-slate-200 pb-4 mb-6">
                        <nav className="flex space-x-6" aria-label="Tabs">
                            <button
                                type="button"
                                onClick={() => handleTabChange('items', 'products')}
                                className={`pb-2 text-base font-bold transition-all border-b-2 ${
                                    activeTab === 'items'
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                                }`}
                            >
                                Barang & Jasa
                            </button>

                            <button
                                type="button"
                                onClick={() => handleTabChange('gudang', 'warehouses')}
                                className={`pb-2 text-base font-bold transition-all border-b-2 flex items-center gap-2 ${
                                    activeTab === 'gudang'
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                                }`}
                            >
                                <span>Gudang</span>
                                {stats.pending_requests_count > 0 && (
                                    <span className="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">
                                        {stats.pending_requests_count}
                                    </span>
                                )}
                            </button>

                            <button
                                type="button"
                                onClick={() => handleTabChange('master', 'categories')}
                                className={`pb-2 text-base font-bold transition-all border-b-2 ${
                                    activeTab === 'master'
                                        ? 'border-indigo-600 text-indigo-600'
                                        : 'border-transparent text-slate-500 hover:text-slate-700 hover:border-slate-300'
                                }`}
                            >
                                Aturan Harga
                            </button>
                        </nav>
                    </div>

                    {/* Tab Views */}
                    {activeTab === 'items' && (
                        <ItemsTab
                            products={products}
                            categories={categories}
                            brands={brands}
                            uoms={uoms}
                            filters={filters}
                        />
                    )}

                    {activeTab === 'gudang' && (
                        <WarehouseTab
                            subTab={subTab}
                            warehouses={warehouses}
                            stockBalances={stockBalances}
                            stockRequests={stockRequests}
                        />
                    )}

                    {activeTab === 'master' && (
                        <MasterDataTab
                            subTab={subTab}
                            categories={categories}
                            uoms={uoms}
                            brands={brands}
                        />
                    )}
                </div>
            </div>
        </CompanyLayout>
    );
}
