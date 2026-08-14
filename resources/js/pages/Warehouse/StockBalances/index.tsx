import { Head, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import PageHeader from '@/components/ui/page-header';
import SelectInput from '@/components/ui/select-input';
import TextInput from '@/components/ui/text-input';
import CompanyLayout from '@/layouts/company/company-layout';
import { stockBalanceLabels } from './types';
import type { StockBalance, WarehouseOption } from './types';

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    balances: Paginated<StockBalance>;
    warehouses: WarehouseOption[];
    filters: {
        warehouse_id?: string;
        search?: string;
    };
};

export default function Index({ balances, warehouses, filters }: Props) {
    const applyFilters = (patch: Partial<Props['filters']>) => {
        router.get('/warehouse/stock-balances', patch, { preserveState: true });
    };

    const columns: DataTableColumn<StockBalance>[] = [
        {
            key: 'product',
            header: 'Produk',
            render: (balance) => balance.product_variant?.product?.name ?? '-',
            cellClassName: 'font-medium text-gray-900 dark:text-white',
        },
        {
            key: 'variant',
            header: 'Varian',
            render: (balance) => balance.product_variant?.variant_name ?? '-',
        },
        {
            key: 'sku',
            header: 'SKU',
            render: (balance) => balance.product_variant?.sku ?? '-',
        },
        {
            key: 'warehouse',
            header: 'Gudang',
            render: (balance) => balance.warehouse?.name ?? '-',
        },
        {
            key: 'qty_on_hand',
            header: 'Qty On Hand',
            align: 'right' as const,
            render: (balance) => balance.qty_on_hand.toLocaleString('id-ID'),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={stockBalanceLabels.plural} />

            <div className="space-y-6">
                <PageHeader title={stockBalanceLabels.plural} description={stockBalanceLabels.description} />

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
                            placeholder="Cari produk / SKU..."
                        />
                    </div>
                    <div className="w-56">
                        <SelectInput
                            id="warehouse_id"
                            value={filters.warehouse_id ?? ''}
                            onChange={(e) => applyFilters({ ...filters, warehouse_id: e.target.value })}
                        >
                            <option value="">Semua Gudang</option>
                            {warehouses.map((warehouse) => (
                                <option key={warehouse.id} value={warehouse.id}>
                                    {warehouse.name}
                                </option>
                            ))}
                        </SelectInput>
                    </div>
                </div>

                <DataTable
                    columns={columns}
                    rows={balances.data}
                    getRowKey={(balance) => balance.id}
                    emptyMessage="Belum ada saldo stok. Saldo akan muncul setelah stock balance dibuat."
                />
            </div>
        </CompanyLayout>
    );
}
