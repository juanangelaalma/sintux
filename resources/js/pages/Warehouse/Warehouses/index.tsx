import { Head, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import { warehouseLabels, warehouseTypeLabels } from './types';
import type { Warehouse } from './types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    warehouses: Paginated<Warehouse>;
    filters: {
        search?: string;
        warehouse_type?: string;
    };
};

export default function Index({ warehouses, filters }: Props) {
    const applyFilters = (patch: Partial<Props['filters']>) => {
        router.get('/warehouse/warehouses', patch, { preserveState: true });
    };

    const handleDelete = (warehouse: Warehouse) => {
        if (confirm(`Remove ${warehouse.name}?`)) {
            router.delete(`/warehouse/warehouses/${warehouse.id}`);
        }
    };

    const columns: DataTableColumn<Warehouse>[] = [
        {
            key: 'code',
            header: 'Kode',
            render: (warehouse) => warehouse.code,
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'name',
            header: 'Nama',
            render: (warehouse) => warehouse.name,
        },
        {
            key: 'branch',
            header: 'Branch',
            render: (warehouse) => warehouse.branch?.name ?? '-',
        },
        {
            key: 'type',
            header: 'Tipe',
            render: (warehouse) => warehouseTypeLabels[warehouse.warehouse_type],
        },
        {
            key: 'status',
            header: 'Status',
            render: (warehouse) => (
                <span
                    className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${
                        warehouse.is_active
                            ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400'
                            : 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400'
                    }`}
                >
                    {warehouse.is_active ? 'Aktif' : 'Tidak Aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right' as const,
            cellClassName: 'font-medium',
            render: (warehouse) => (
                <div className="space-x-3">
                    <a
                        href={`/warehouse/warehouses/${warehouse.id}/edit`}
                        className="text-brand-500 hover:text-brand-600"
                    >
                        Edit
                    </a>
                    <button
                        onClick={() => handleDelete(warehouse)}
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
            <Head title={warehouseLabels.plural} />

            <div className="space-y-6">
                <PageHeader
                    title={warehouseLabels.plural}
                    description={warehouseLabels.description}
                    actions={
                        <a href="/warehouse/warehouses/create">
                            <Button>Tambah {warehouseLabels.singular}</Button>
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
                            placeholder="Cari kode / nama..."
                        />
                    </div>
                    <div className="w-48">
                        <SelectInput
                            id="warehouse_type"
                            value={filters.warehouse_type ?? ''}
                            onChange={(e) => applyFilters({ ...filters, warehouse_type: e.target.value })}
                        >
                            <option value="">Semua Tipe</option>
                            <option value="consignment">Konsinyasi</option>
                            <option value="regular">Reguler</option>
                            <option value="general">Umum</option>
                        </SelectInput>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    rows={warehouses.data}
                    getRowKey={(warehouse) => warehouse.id}
                    emptyMessage="Belum ada gudang. Klik Tambah Gudang untuk membuat."
                />
            </div>
        </CompanyLayout>
    );
}
