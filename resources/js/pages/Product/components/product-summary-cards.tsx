import React from 'react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';

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
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {/* Card 1: Stok Tersedia */}
            <Card className="border-l-4 border-l-emerald-500">
                <CardHeader>
                    <CardTitle className="text-emerald-700 dark:text-emerald-400">
                        Stok Tersedia
                    </CardTitle>
                    <span className="rounded-md bg-emerald-50 p-1.5 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                </CardHeader>
                <CardContent>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Total Produk / Varian</p>
                    <p className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {stats.available_count}
                    </p>
                </CardContent>
            </Card>

            {/* Card 2: Stok Segera Habis */}
            <Card className="border-l-4 border-l-amber-500">
                <CardHeader>
                    <CardTitle className="text-amber-700 dark:text-amber-400">
                        Stok Segera Habis
                    </CardTitle>
                    <span className="rounded-md bg-amber-50 p-1.5 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                    </span>
                </CardHeader>
                <CardContent>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Total Produk / Varian</p>
                    <p className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {stats.low_stock_count}
                    </p>
                </CardContent>
            </Card>

            {/* Card 3: Stok Habis */}
            <Card className="border-l-4 border-l-rose-500">
                <CardHeader>
                    <CardTitle className="text-rose-700 dark:text-rose-400">
                        Stok Habis
                    </CardTitle>
                    <span className="rounded-md bg-rose-50 p-1.5 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </span>
                </CardHeader>
                <CardContent>
                    <p className="text-xs text-gray-500 dark:text-gray-400">Total Produk / Varian</p>
                    <p className="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                        {stats.out_of_stock_count}
                    </p>
                </CardContent>
            </Card>

            {/* Card 4: Gudang & Request */}
            <Card className="border-l-4 border-l-indigo-500">
                <CardHeader>
                    <CardTitle className="text-indigo-700 dark:text-indigo-400">
                        Gudang & Pending
                    </CardTitle>
                    <span className="rounded-md bg-indigo-50 p-1.5 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </span>
                </CardHeader>
                <CardContent>
                    <div className="flex items-baseline justify-between">
                        <div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">Gudang Aktif</p>
                            <p className="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {stats.warehouses_count}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-gray-500 dark:text-gray-400">Pending Request</p>
                            <p className="mt-1 text-2xl font-semibold text-amber-600 dark:text-amber-400">
                                {stats.pending_requests_count}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
};
