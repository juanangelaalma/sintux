import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';

type Category = { id: number; name: string };
type Uom = { id: number; name: string; code: string };

type Product = {
    id: number;
    code: string;
    name: string;
    barcode?: string | null;
    product_type?: 'single' | 'bundle';
    selling_price?: number;
    purchase_price?: number;
    category?: Category;
    uom?: Uom;
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
    categories,
    filters,
}) => {
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '');
    const [productType, setProductType] = useState(filters.product_type ?? '');

    const applySearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/product',
            { tab: 'items', search, category_id: categoryId, product_type: productType },
            { preserveState: true },
        );
    };

    const changePage = (page: number) => {
        router.get(
            '/product',
            { tab: 'items', search, category_id: categoryId, product_type: productType, page },
            { preserveState: true },
        );
    };

    const handleDelete = (product: Product) => {
        if (confirm(`Hapus produk ${product.name}?`)) {
            router.delete(`/product/products/${product.id}`);
        }
    };

    const columns: DataTableColumn<Product>[] = [
        {
            key: 'name',
            header: 'Nama Produk',
            render: (product) => (
                <div>
                    <Link
                        href={`/product/products/${product.id}/edit`}
                        className="font-semibold text-indigo-600 hover:text-indigo-800"
                    >
                        {product.name}
                    </Link>
                    {product.barcode && (
                        <div className="text-xs text-slate-500 font-mono">
                            Barcode: {product.barcode}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'code',
            header: 'Kode / SKU',
            render: (product) => <span className="font-mono text-xs font-semibold">{product.code}</span>,
        },
        {
            key: 'type',
            header: 'Tipe Produk',
            render: (product) =>
                product.product_type === 'bundle' ? (
                    <span className="inline-flex items-center rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-semibold text-purple-700 ring-1 ring-inset ring-purple-600/20">
                        BUNDLE
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-inset ring-blue-600/20">
                        SINGLE
                    </span>
                ),
        },
        {
            key: 'category',
            header: 'Kategori',
            render: (product) => product.category?.name ?? '-',
        },
        {
            key: 'uom',
            header: 'Satuan',
            render: (product) => (product.uom ? `${product.uom.name} (${product.uom.code})` : '-'),
        },
        {
            key: 'selling_price',
            header: 'Harga Jual',
            align: 'right',
            render: (product) =>
                product.selling_price
                    ? `Rp ${Number(product.selling_price).toLocaleString('id-ID')}`
                    : '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (product) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        product.is_active
                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                            : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'
                    }`}
                >
                    {product.is_active ? 'Aktif' : 'Non-aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (product) => (
                <div className="flex items-center justify-end gap-2">
                    <Link href={`/product/products/${product.id}/edit`}>
                        <Button variant="secondary">
                            Edit
                        </Button>
                    </Link>
                    <Button variant="danger" onClick={() => handleDelete(product)}>
                        Hapus
                    </Button>
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {/* Filter Bar */}
            <form onSubmit={applySearch} className="flex flex-col gap-3 sm:flex-row sm:items-center justify-between bg-slate-50 p-4 rounded-xl border border-slate-200">
                <div className="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                    <div className="w-full sm:w-72">
                        <TextInput
                            placeholder="Cari nama, SKU, barcode..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />
                    </div>
                    <div className="w-full sm:w-48">
                        <SelectInput
                            value={categoryId}
                            onChange={(e) => setCategoryId(e.target.value)}
                        >
                            <option value="">Semua Kategori</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                    <div className="w-full sm:w-48">
                        <SelectInput
                            value={productType}
                            onChange={(e) => setProductType(e.target.value)}
                        >
                            <option value="">Semua Tipe</option>
                            <option value="single">Single</option>
                            <option value="bundle">Bundle</option>
                        </SelectInput>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Button type="submit" variant="primary">
                        Filter
                    </Button>
                    <Link href="/product/products/create">
                        <Button type="button" variant="secondary">
                            + Tambah Produk
                        </Button>
                    </Link>
                </div>
            </form>

            {/* Table */}
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
        </div>
    );
};
