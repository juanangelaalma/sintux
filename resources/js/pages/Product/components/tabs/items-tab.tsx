import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';

type Category = { id: number; name: string };
type Brand = { id: number; name: string };
type Uom = { id: number; name: string; code: string };

type ProductVariant = {
    id: number;
    sku: string;
    variant_name: string;
    is_active: boolean;
};

type Product = {
    id: number;
    code: string;
    name: string;
    category?: Category;
    brand?: Brand;
    uom?: Uom;
    variants?: ProductVariant[];
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
    brands: Brand[];
    uoms: Uom[];
    filters: {
        search?: string;
        category_id?: string;
        brand_id?: string;
    };
};

export const ItemsTab: React.FC<Props> = ({
    products,
    categories,
    brands,
    filters,
}) => {
    const [search, setSearch] = useState(filters.search ?? '');
    const [categoryId, setCategoryId] = useState(filters.category_id ?? '');
    const [brandId, setBrandId] = useState(filters.brand_id ?? '');

    const applySearch = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(
            '/product',
            { tab: 'items', search, category_id: categoryId, brand_id: brandId },
            { preserveState: true },
        );
    };

    const changePage = (page: number) => {
        router.get(
            '/product',
            { tab: 'items', search, category_id: categoryId, brand_id: brandId, page },
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
                    {product.variants && product.variants.length > 0 && (
                        <div className="text-xs text-slate-500 mt-0.5">
                            {product.variants.length} varian ({product.variants.map((v) => v.variant_name).join(', ')})
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'code',
            header: 'Kode Produk',
            render: (product) => <span className="font-mono text-xs font-semibold">{product.code}</span>,
        },
        {
            key: 'category',
            header: 'Kategori',
            render: (product) => product.category?.name ?? '-',
        },
        {
            key: 'brand',
            header: 'Brand',
            render: (product) => product.brand?.name ?? '-',
        },
        {
            key: 'uom',
            header: 'Satuan (UOM)',
            render: (product) => product.uom ? `${product.uom.name} (${product.uom.code})` : '-',
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
                            placeholder="Cari produk / kode..."
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
                            value={brandId}
                            onChange={(e) => setBrandId(e.target.value)}
                        >
                            <option value="">Semua Brand</option>
                            {brands.map((b) => (
                                <option key={b.id} value={b.id}>
                                    {b.name}
                                </option>
                            ))}
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
