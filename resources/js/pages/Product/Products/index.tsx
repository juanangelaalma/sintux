import { Head, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import { productLabels } from './types';
import type { Product } from './types';

type CategoryOption = { id: number; name: string };
type BrandOption = { id: number; name: string };
type UomOption = { id: number; name: string; code: string };

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    products: Paginated<Product>;
    categories: CategoryOption[];
    brands: BrandOption[];
    uoms: UomOption[];
    filters: {
        search?: string;
        category_id?: string;
        brand_id?: string;
        is_active?: boolean;
    };
};

export default function Index({ products, categories, brands, uoms, filters }: Props) {
    const handleDelete = (product: Product) => {
        if (confirm(`Remove ${product.name}?`)) {
            router.delete(`/product/products/${product.id}`);
        }
    };

    const applyFilters = (patch: Partial<typeof filters>) => {
        router.get('/product/products', patch, { preserveState: true });
    };

    const changePage = (page: number) => {
        router.get('/product/products', { ...filters, page }, { preserveState: true });
    };

    const columns: DataTableColumn<Product>[] = [
        {
            key: 'code',
            header: 'Kode',
            render: (product) => product.code,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'name',
            header: 'Nama',
            render: (product) => product.name,
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
            header: 'Satuan',
            render: (product) => (product.uom ? `${product.uom.name} (${product.uom.code})` : '-'),
        },
        {
            key: 'status',
            header: 'Status',
            render: (product) => (
                <span
                    className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                        product.is_active
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                    }`}
                >
                    {product.is_active ? 'Aktif' : 'Tidak Aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right' as const,
            cellClassName: 'font-medium',
            render: (product: Product) => (
                <div className="space-x-3">
                    <a
                        href={`/product/products/${product.id}/edit`}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </a>
                    <button
                        onClick={() => handleDelete(product)}
                        className="text-red-500 hover:text-red-600"
                    >
                        Hapus
                    </button>
                </div>
            ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={productLabels.plural} />

            <div className="space-y-6">
                <PageHeader
                    title={productLabels.plural}
                    description={productLabels.description}
                    actions={
                        <a href="/product/products/create">
                            <Button>Tambah {productLabels.singular}</Button>
                        </a>
                    }
                />

                <div className="flex flex-wrap gap-3">
                    <div className="w-64">
                        <TextInput
                            id="search"
                            defaultValue={filters.search ?? ''}
                            onChange={(e) => {
                                if (e.target.value.length >= 2 || e.target.value === '') {
                                    applyFilters({ ...filters, search: e.target.value });
                                }
                            }}
                            placeholder="Cari nama / kode..."
                        />
                    </div>
                    <div className="w-48">
                        <SelectInput
                            id="category_id"
                            value={filters.category_id ?? ''}
                            onChange={(e) => applyFilters({ ...filters, category_id: e.target.value })}
                        >
                            <option value="">Semua Kategori</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                    <div className="w-48">
                        <SelectInput
                            id="brand_id"
                            value={filters.brand_id ?? ''}
                            onChange={(e) => applyFilters({ ...filters, brand_id: e.target.value })}
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

                <DataTable
                    columns={columns}
                    rows={products.data}
                    getRowKey={(product) => product.id}
                    emptyMessage={`Belum ada ${productLabels.plural.toLowerCase()}. Klik "Tambah ${productLabels.singular}" untuk membuat.`}
                    pagination={{
                        currentPage: products.current_page,
                        lastPage: products.last_page,
                        total: products.total,
                        perPage: products.per_page,
                    }}
                    onPageChange={changePage}
                />
            </div>
        </CompanyLayout>
    );
}