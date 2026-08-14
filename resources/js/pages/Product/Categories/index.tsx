import { Head, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { categoryLabels } from './types';
import type { ProductCategory } from './types';

type Props = {
    categories: {
        data: ProductCategory[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    filters: {
        search?: string;
        is_active?: boolean;
    };
};

export default function Index({ categories, filters }: Props) {
    const handleDelete = (category: ProductCategory) => {
        if (confirm(`Remove ${category.name}?`)) {
            router.delete(`/product/categories/${category.id}`);
        }
    };

    const changePage = (page: number) => {
        router.get('/product/categories', { ...filters, page }, { preserveState: true });
    };

    const columns: DataTableColumn<ProductCategory>[] = [
        {
            key: 'name',
            header: 'Nama',
            render: (category) => category.name,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'status',
            header: 'Status',
            render: (category) => (
                <span
                    className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                        category.is_active
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                    }`}
                >
                    {category.is_active ? 'Aktif' : 'Tidak Aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right' as const,
            cellClassName: 'font-medium',
            render: (category: ProductCategory) => (
                <div className="space-x-3">
                    <a
                        href={`/product/categories/${category.id}/edit`}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </a>
                    <button
                        onClick={() => handleDelete(category)}
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
            <Head title={categoryLabels.plural} />

            <div className="space-y-6">
                <PageHeader
                    title={categoryLabels.plural}
                    description={categoryLabels.description}
                    actions={
                        <a href="/product/categories/create">
                            <Button>Tambah {categoryLabels.singular}</Button>
                        </a>
                    }
                />
                <DataTable
                    columns={columns}
                    rows={categories.data}
                    getRowKey={(category) => category.id}
                    emptyMessage={`Belum ada ${categoryLabels.plural.toLowerCase()}. Klik "Tambah ${categoryLabels.singular}" untuk membuat.`}
                    pagination={{
                        currentPage: categories.current_page,
                        lastPage: categories.last_page,
                        total: categories.total,
                        perPage: categories.per_page,
                    }}
                    onPageChange={changePage}
                />
            </div>
        </CompanyLayout>
    );
}