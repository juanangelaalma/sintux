import React, { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { ProductActionsDropdown } from './components/product-actions-dropdown';
import { ProductSummaryCards } from './components/product-summary-cards';
import { ItemsTab } from './components/tabs/items-tab';
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
    activeTab: string; // 'items' | 'gudang' | 'pricing'
    subTab: string;
    filters: Record<string, any>;
    products: any;
    categories: any[];
    uoms: any[];
    warehouses: any[];
    stockBalances: any;
    stockRequests: any;
    stockAdjustments?: any;
    stockTransfers?: any;
};

export default function ProductIndex({
    stats,
    activeTab,
    subTab,
    filters,
    products,
    categories,
    uoms,
    warehouses,
    stockBalances,
    stockRequests,
    stockAdjustments,
    stockTransfers,
}: Props) {
    const [showBanner, setShowBanner] = useState(true);

    const handleTabChange = (tab: string, defaultSub: string) => {
        router.get(
            '/product',
            { tab, sub: defaultSub },
            { preserveState: true },
        );
    };

    return (
        <CompanyLayout>
            <Head title="Produk" />

            <div className="space-y-6">
                {/* Header & Quick Action */}
                <PageHeader
                    title="Produk"
                    description="Manajemen katalog barang, persediaan stok, HPP, paket bundle, dan pengajuan transfer."
                    actions={<ProductActionsDropdown />}
                />

                {/* Level-1 Top Navigation Bar (Matching Image 1) */}
                <div className="border-b border-slate-200 pb-2">
                    <nav className="flex space-x-6 text-sm font-semibold">
                        <button
                            type="button"
                            onClick={() => handleTabChange('items', 'products')}
                            className={`border-b-2 pb-2 transition-all ${
                                activeTab === 'items'
                                    ? 'border-indigo-600 font-bold text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Barang & jasa
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                handleTabChange('gudang', 'warehouses')
                            }
                            className={`flex items-center gap-2 border-b-2 pb-2 transition-all ${
                                activeTab === 'gudang'
                                    ? 'border-indigo-600 font-bold text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
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
                            onClick={() => handleTabChange('pricing', 'rules')}
                            className={`border-b-2 pb-2 transition-all ${
                                activeTab === 'pricing'
                                    ? 'border-indigo-600 font-bold text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700'
                            }`}
                        >
                            Aturan harga
                        </button>
                    </nav>
                </div>

                {/* Main Content Areas */}
                {activeTab === 'items' && (
                    <div className="space-y-6">
                        {/* Summary KPI Cards (Image 1 Style) */}
                        <ProductSummaryCards stats={stats} />

                        {/* Items Table Container */}
                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <ItemsTab
                                products={products}
                                categories={categories}
                                uoms={uoms}
                                filters={filters}
                            />
                        </div>
                    </div>
                )}

                {activeTab === 'gudang' && (
                    <div className="space-y-6">
                        <ProductSummaryCards stats={stats} />
                        <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                            <WarehouseTab
                                subTab={subTab}
                                warehouses={warehouses}
                                stockBalances={stockBalances}
                                stockRequests={stockRequests}
                                stockAdjustments={stockAdjustments}
                                stockTransfers={stockTransfers}
                                filters={filters}
                            />
                        </div>
                    </div>
                )}

                {activeTab === 'pricing' && (
                    <div className="space-y-3 rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                        <h3 className="text-base font-bold text-slate-800">
                            Aturan Harga & Multi Harga
                        </h3>
                        <p className="mx-auto max-w-md text-xs text-slate-500">
                            Fitur Aturan Harga memungkinkan pengaturan diskon
                            bertingkat, daftar harga grosir, dan harga per
                            kelompok pelanggan.
                        </p>
                    </div>
                )}
            </div>
        </CompanyLayout>
    );
}
