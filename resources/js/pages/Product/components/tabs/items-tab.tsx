import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import { CategoryManagementModal } from '../category-management-modal';

type Category = { id: number; name: string; products_count?: number };
type Uom = { id: number; name: string; code: string };

type Product = {
    id: number;
    code: string;
    name: string;
    barcode?: string | null;
    product_type?: 'single' | 'bundle';
    selling_price?: number;
    purchase_price?: number;
    min_stock?: number;
    category?: Category;
    uom?: Uom;
    variants?: Array<{ id: number; sku: string }>;
    total_stock?: number;
    is_active: boolean;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    products: Paginated<Product>;
    categories: Category[];
    uoms: Uom[];
    filters: {
        search?: string;
        category_id?: string;
        product_type?: string;
    };
};

export const ItemsTab: React.FC<Props> = ({
    products,
    categories = [],
    filters = {},
}) => {
    const [level2Tab, setLevel2Tab] = useState<'daftar' | 'penyesuaian' | 'approval'>('daftar');
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/product',
            { tab: 'items', search, category_id: filters.category_id },
            { preserveState: true }
        );
    };

    const changePage = (page: number) => {
        router.get(
            '/product',
            { tab: 'items', search, page },
            { preserveState: true, preserveScroll: true }
        );
    };

    const toggleSelectAll = () => {
        if (selectedIds.length === products.data.length) {
            setSelectedIds([]);
        } else {
            setSelectedIds(products.data.map((p) => p.id));
        }
    };

    const toggleSelectRow = (id: number) => {
        if (selectedIds.includes(id)) {
            setSelectedIds(selectedIds.filter((item) => item !== id));
        } else {
            setSelectedIds([...selectedIds, id]);
        }
    };

    const columns: DataTableColumn<Product>[] = [
        {
            key: 'checkbox',
            header: (
                <input
                    type="checkbox"
                    checked={products.data.length > 0 && selectedIds.length === products.data.length}
                    onChange={toggleSelectAll}
                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
            ),
            render: (product) => (
                <input
                    type="checkbox"
                    checked={selectedIds.includes(product.id)}
                    onChange={() => toggleSelectRow(product.id)}
                    className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                />
            ),
        },
        {
            key: 'name',
            header: 'Nama produk ↕',
            render: (product) => (
                <Link
                    href={`/product/products/${product.id}/edit`}
                    className="font-bold text-indigo-600 hover:text-indigo-800"
                >
                    {product.name}
                </Link>
            ),
        },
        {
            key: 'code',
            header: 'Kode produk ↕',
            render: (product) => <span className="font-mono text-xs text-slate-700">{product.code}</span>,
        },
        {
            key: 'category',
            header: 'Kategori produk ↕',
            render: (product) => product.category?.name ?? '-',
        },
        {
            key: 'total_stock',
            header: 'Total stok ↕',
            render: (product) => (
                <span className="font-bold text-slate-900">{product.total_stock ?? 0}</span>
            ),
        },
        {
            key: 'min_stock',
            header: 'Batas minimum',
            render: (product) => product.min_stock ?? 0,
        },
        {
            key: 'unit',
            header: 'Unit ↕',
            render: (product) => product.uom?.code ?? 'PCS',
        },
        {
            key: 'hpp',
            header: 'HPP saat ini ↕',
            align: 'right',
            render: (product) => (
                <span>
                    {product.purchase_price
                        ? `Rp${Number(product.purchase_price).toLocaleString('id-ID')},00`
                        : 'Rp0,00'}
                </span>
            ),
        },
        {
            key: 'last_purchase_price',
            header: 'Harga beli terakhir ↕',
            align: 'right',
            render: (product) => (
                <span>
                    {product.purchase_price
                        ? `Rp${Number(product.purchase_price).toLocaleString('id-ID')},00`
                        : 'Rp0,00'}
                </span>
            ),
        },
        {
            key: 'purchase_price',
            header: 'Harga beli ↕',
            align: 'right',
            render: (product) => (
                <span>
                    {product.purchase_price
                        ? `Rp${Number(product.purchase_price).toLocaleString('id-ID')},00`
                        : 'Rp0,00'}
                </span>
            ),
        },
        {
            key: 'selling_price',
            header: 'Harga jual ↕',
            align: 'right',
            render: (product) => (
                <span>
                    {product.selling_price
                        ? `Rp${Number(product.selling_price).toLocaleString('id-ID')},00`
                        : 'Rp0,00'}
                </span>
            ),
        },
    ];

    return (
        <div className="space-y-6">
            {/* Level-2 Sub Tabs (Matching Image 1) */}
            <div className="border-b border-slate-200 pb-2">
                <nav className="flex space-x-6 text-sm font-semibold">
                    <button
                        type="button"
                        onClick={() => setLevel2Tab('daftar')}
                        className={`pb-2 border-b-2 transition-all ${
                            level2Tab === 'daftar'
                                ? 'border-indigo-600 text-indigo-600 font-bold'
                                : 'border-transparent text-slate-500 hover:text-slate-700'
                        }`}
                    >
                        Daftar produk
                    </button>
                    <Link
                        href="/warehouse/adjustments"
                        className="pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-all"
                    >
                        Daftar penyesuaian stok
                    </Link>
                    <Link
                        href="/warehouse/stock-requests"
                        className="pb-2 border-b-2 border-transparent text-slate-500 hover:text-slate-700 transition-all"
                    >
                        Membutuhkan persetujuan
                    </Link>
                </nav>
            </div>

            {/* Toolbar Action Bar (Matching Image 1) */}
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center justify-between">
                {/* Left Actions */}
                <div className="flex items-center gap-3">
                    <button
                        type="button"
                        className="rounded-lg border border-slate-300 p-2 text-slate-600 hover:bg-slate-50"
                        title="Tampilan Grid"
                    >
                        <svg className="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        onClick={() => setShowCategoryModal(true)}
                        className="rounded-lg border border-indigo-600/40 bg-white px-3.5 py-1.5 text-xs font-semibold text-indigo-600 hover:bg-indigo-50 shadow-sm transition-colors"
                    >
                        Atur kategori produk
                    </button>
                </div>

                {/* Right Actions */}
                <form onSubmit={handleSearchSubmit} className="flex flex-wrap items-center gap-2">
                    <Button type="button" variant="secondary" className="text-xs px-3 py-1.5">
                        Impor
                    </Button>

                    <Button type="button" variant="secondary" className="text-xs px-3 py-1.5">
                        Ekspor
                    </Button>

                    <div className="relative w-48 sm:w-56">
                        <span className="absolute inset-y-0 left-0 flex items-center pl-2.5 text-slate-400">
                            <svg className="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </span>
                        <input
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari produk"
                            className="w-full rounded-lg border border-slate-300 pl-8 pr-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
                        />
                    </div>

                    <Button type="submit" variant="secondary" className="text-xs px-3 py-1.5 flex items-center gap-1">
                        <svg className="size-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter
                    </Button>
                </form>
            </div>

            {/* Product Table (Image 1 style) */}
            <DataTable<Product>
                columns={columns}
                rows={products.data}
                getRowKey={(row) => row.id}
                emptyMessage="Belum ada data produk."
                pagination={{
                    currentPage: products.current_page,
                    lastPage: products.last_page,
                    total: products.total,
                    perPage: products.per_page,
                }}
                onPageChange={changePage}
            />

            {/* Category Management Pop-up Modal */}
            <CategoryManagementModal
                show={showCategoryModal}
                onClose={() => setShowCategoryModal(false)}
                categories={categories}
            />
        </div>
    );
};
