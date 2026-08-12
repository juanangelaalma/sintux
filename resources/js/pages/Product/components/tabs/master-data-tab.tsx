import React from 'react';
import { Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';

type Category = { id: number; name: string; is_active: boolean };
type Uom = { id: number; name: string; code: string; is_active: boolean };
type Brand = { id: number; name: string; is_active: boolean };

type Props = {
    subTab: string;
    categories: Category[];
    uoms: Uom[];
    brands: Brand[];
};

export const MasterDataTab: React.FC<Props> = ({
    subTab,
    categories,
    uoms,
    brands,
}) => {
    const handleSubTabChange = (newSub: string) => {
        router.get('/product', { tab: 'master', sub: newSub }, { preserveState: true });
    };

    const handleDeleteCategory = (cat: Category) => {
        if (confirm(`Hapus kategori ${cat.name}?`)) {
            router.delete(`/product/categories/${cat.id}`);
        }
    };

    const handleDeleteUom = (uom: Uom) => {
        if (confirm(`Hapus satuan ${uom.name}?`)) {
            router.delete(`/product/uoms/${uom.id}`);
        }
    };

    const handleDeleteBrand = (brand: Brand) => {
        if (confirm(`Hapus brand ${brand.name}?`)) {
            router.delete(`/product/brands/${brand.id}`);
        }
    };

    const categoryColumns: DataTableColumn<Category>[] = [
        {
            key: 'name',
            header: 'Nama Kategori',
            render: (c) => <span className="font-semibold text-slate-900">{c.name}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (c) => (
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${c.is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-rose-50 text-rose-700'}`}>
                    {c.is_active ? 'Aktif' : 'Non-aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (c) => (
                <div className="flex items-center justify-end gap-2">
                    <Link href={`/product/categories/${c.id}/edit`}>
                        <Button variant="secondary">Edit</Button>
                    </Link>
                    <Button variant="danger" onClick={() => handleDeleteCategory(c)}>Hapus</Button>
                </div>
            ),
        },
    ];

    const uomColumns: DataTableColumn<Uom>[] = [
        {
            key: 'code',
            header: 'Kode Satuan',
            render: (u) => <span className="font-mono text-xs font-semibold">{u.code}</span>,
        },
        {
            key: 'name',
            header: 'Nama Satuan',
            render: (u) => <span className="font-semibold text-slate-900">{u.name}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (u) => (
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${u.is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-rose-50 text-rose-700'}`}>
                    {u.is_active ? 'Aktif' : 'Non-aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (u) => (
                <div className="flex items-center justify-end gap-2">
                    <Link href={`/product/uoms/${u.id}/edit`}>
                        <Button variant="secondary">Edit</Button>
                    </Link>
                    <Button variant="danger" onClick={() => handleDeleteUom(u)}>Hapus</Button>
                </div>
            ),
        },
    ];

    const brandColumns: DataTableColumn<Brand>[] = [
        {
            key: 'name',
            header: 'Nama Brand',
            render: (b) => <span className="font-semibold text-slate-900">{b.name}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (b) => (
                <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${b.is_active ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20' : 'bg-rose-50 text-rose-700'}`}>
                    {b.is_active ? 'Aktif' : 'Non-aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (b) => (
                <div className="flex items-center justify-end gap-2">
                    <Link href={`/product/brands/${b.id}/edit`}>
                        <Button variant="secondary">Edit</Button>
                    </Link>
                    <Button variant="danger" onClick={() => handleDeleteBrand(b)}>Hapus</Button>
                </div>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {/* Sub Tabs Navigation */}
            <div className="flex items-center gap-2 border-b border-slate-200 pb-3">
                <button
                    type="button"
                    onClick={() => handleSubTabChange('categories')}
                    className={`px-3 py-1.5 text-sm font-semibold rounded-lg transition-all ${
                        subTab === 'categories'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Kategori Produk ({categories.length})
                </button>
                <button
                    type="button"
                    onClick={() => handleSubTabChange('uoms')}
                    className={`px-3 py-1.5 text-sm font-semibold rounded-lg transition-all ${
                        subTab === 'uoms'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Satuan UOM ({uoms.length})
                </button>
                <button
                    type="button"
                    onClick={() => handleSubTabChange('brands')}
                    className={`px-3 py-1.5 text-sm font-semibold rounded-lg transition-all ${
                        subTab === 'brands'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Brand ({brands.length})
                </button>
            </div>

            {/* Sub Tab Content */}
            {subTab === 'categories' && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/product/categories/create">
                            <Button variant="primary">+ Tambah Kategori</Button>
                        </Link>
                    </div>
                    <DataTable<Category>
                        columns={categoryColumns}
                        rows={categories}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada kategori produk."
                    />
                </div>
            )}

            {subTab === 'uoms' && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/product/uoms/create">
                            <Button variant="primary">+ Tambah Satuan (UOM)</Button>
                        </Link>
                    </div>
                    <DataTable<Uom>
                        columns={uomColumns}
                        rows={uoms}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada satuan uom."
                    />
                </div>
            )}

            {subTab === 'brands' && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/product/brands/create">
                            <Button variant="primary">+ Tambah Brand</Button>
                        </Link>
                    </div>
                    <DataTable<Brand>
                        columns={brandColumns}
                        rows={brands}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada brand."
                    />
                </div>
            )}
        </div>
    );
};
