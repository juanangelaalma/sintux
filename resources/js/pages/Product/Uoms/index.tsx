import { Head, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { uomLabels } from './types';
import type { Uom } from './types';

type Props = {
    uoms: {
        data: Uom[];
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

export default function Index({ uoms, filters }: Props) {
    const handleDelete = (uom: Uom) => {
        if (confirm(`Remove ${uom.name}?`)) {
            router.delete(`/product/uoms/${uom.id}`);
        }
    };

    const changePage = (page: number) => {
        router.get('/product/uoms', { ...filters, page }, { preserveState: true });
    };

    const columns: DataTableColumn<Uom>[] = [
        {
            key: 'name',
            header: 'Nama',
            render: (uom) => uom.name,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'code',
            header: 'Kode',
            render: (uom) => uom.code,
        },
        {
            key: 'status',
            header: 'Status',
            render: (uom) => (
                <span
                    className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                        uom.is_active
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                    }`}
                >
                    {uom.is_active ? 'Aktif' : 'Tidak Aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right' as const,
            cellClassName: 'font-medium',
            render: (uom: Uom) => (
                <div className="space-x-3">
                    <a
                        href={`/product/uoms/${uom.id}/edit`}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </a>
                    <button
                        onClick={() => handleDelete(uom)}
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
            <Head title={uomLabels.plural} />

            <div className="space-y-6">
                <PageHeader
                    title={uomLabels.plural}
                    description={uomLabels.description}
                    actions={
                        <a href="/product/uoms/create">
                            <Button>Tambah {uomLabels.singular}</Button>
                        </a>
                    }
                />
                <DataTable
                    columns={columns}
                    rows={uoms.data}
                    getRowKey={(uom) => uom.id}
                    emptyMessage={`Belum ada ${uomLabels.plural.toLowerCase()}. Klik "Tambah ${uomLabels.singular}" untuk membuat.`}
                    pagination={{
                        currentPage: uoms.current_page,
                        lastPage: uoms.last_page,
                        total: uoms.total,
                        perPage: uoms.per_page,
                    }}
                    onPageChange={changePage}
                />
            </div>
        </CompanyLayout>
    );
}