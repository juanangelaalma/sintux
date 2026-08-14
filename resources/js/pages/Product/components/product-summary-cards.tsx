import React from 'react';

type ProductStats = {
    available_count: number;
    low_stock_count: number;
    out_of_stock_count: number;
    warehouses_count: number;
    pending_requests_count: number;
};

type Props = {
    stats: ProductStats;
};

export const ProductSummaryCards: React.FC<Props> = ({ stats }) => {
    return (
        <div className="space-y-2">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {/* Box 1: Stok Tersedia */}
                <div className="rounded-xl border border-emerald-500/40 bg-emerald-50/20 p-4 shadow-sm space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-semibold text-slate-800">
                            Stok tersedia
                        </span>
                        <div className="rounded-md border border-slate-300 p-1 bg-white text-slate-500">
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-slate-500">Total produk</div>
                        <div className="text-2xl font-bold text-slate-900 mt-0.5">
                            {stats.available_count}
                        </div>
                    </div>
                </div>

                {/* Box 2: Stok Segera Habis */}
                <div className="rounded-xl border border-amber-400 bg-amber-50/30 p-4 shadow-sm space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-semibold text-slate-800">
                            Stok segera habis
                        </span>
                        <div className="rounded-md border border-slate-300 p-1 bg-white text-slate-500">
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-slate-500">Total produk</div>
                        <div className="text-2xl font-bold text-slate-900 mt-0.5">
                            {stats.low_stock_count}
                        </div>
                    </div>
                </div>

                {/* Box 3: Stok Habis */}
                <div className="rounded-xl border border-rose-400 bg-rose-50/20 p-4 shadow-sm space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-semibold text-slate-800">
                            Stok habis
                        </span>
                        <div className="rounded-md border border-slate-300 p-1 bg-white text-slate-500">
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-slate-500">Total produk</div>
                        <div className="text-2xl font-bold text-slate-900 mt-0.5">
                            {stats.out_of_stock_count}
                        </div>
                    </div>
                </div>

                {/* Box 4: Gudang */}
                <div className="rounded-xl border border-indigo-400 bg-indigo-50/20 p-4 shadow-sm space-y-3">
                    <div className="flex items-center justify-between">
                        <span className="text-sm font-semibold text-slate-800">
                            Gudang
                        </span>
                        <div className="rounded-md border border-slate-300 p-1 bg-white text-slate-500">
                            <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <div className="text-xs text-slate-500">Terdaftar</div>
                        <div className="text-2xl font-bold text-slate-900 mt-0.5">
                            {stats.warehouses_count}
                        </div>
                    </div>
                </div>
            </div>

            {/* Footnote text matching Image 1 */}
            <div className="text-right text-[11px] text-slate-400">
                Untuk menampilkan ringkasan produk, centang Monitor persediaan barang saat menambah produk baru
            </div>
        </div>
    );
};
