import { Head, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { brandLabels } from './types';
import type { Brand } from './types';

type Props = {
    brands: {
        data: Brand[];
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

export default function Index({ brands, filters }: Props) {
    const handleDelete = (brand: Brand) => {
        if (confirm(`Remove ${brand.name}?`)) {
            router.delete(`/product/brands/${brand.id}`);
        }
    };

    const columns: DataTableColumn<Brand>[] = [
        {
            key: 'name',
            header: 'Nama',
            render: (brand) => brand.name,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'status',
            header: 'Status',
            render: (brand) => (
                <span
                    className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                        brand.is_active
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                    }`}
                >
                    {brand.is_active ? 'Aktif' : 'Tidak Aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right' as const,
            cellClassName: 'font-medium',
            render: (brand: Brand) => (
                <div className="space-x-3">
                    <a
                        href={`/product/brands/${brand.id}/edit`}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </a>
                    <button
                        onClick={() => handleDelete(brand)}
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
            <Head title={brandLabels.plural} />

            <div className="space-y-6">
                <PageHeader
                    title={brandLabels.plural}
                    description={brandLabels.description}
                    actions={
                        <a href="/product/brands/create">
                            <Button>Tambah {brandLabels.singular}</Button>
                        </a>
                    }
                />
                <DataTable
                    columns={columns}
                    rows={brands.data}
                    getRowKey={(brand) => brand.id}
                    emptyMessage={`Belum ada ${brandLabels.plural.toLowerCase()}. Klik "Tambah ${brandLabels.singular}" untuk membuat.`}
                />
            </div>
        </CompanyLayout>
    );
}