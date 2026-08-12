import React from 'react';
import { Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';

type Branch = { id: number; name: string };

type Warehouse = {
    id: number;
    code: string;
    name: string;
    warehouse_type: string;
    is_active: boolean;
    branch?: Branch;
};

type StockBalance = {
    id: number;
    qty_on_hand: number;
    warehouse_id: number;
    product_variant_id: number;
    warehouse?: Warehouse;
    product_variant?: {
        sku: string;
        variant_name: string;
        product?: { name: string; code: string };
    };
};

type StockRequest = {
    id: number;
    requested_at: string;
    status: string;
    requesting_warehouse?: Warehouse;
    destination_warehouse?: Warehouse;
    requested_by_user?: { name: string };
    items?: Array<{ id: number; qty_requested: number }>;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    subTab: string;
    warehouses: Warehouse[];
    stockBalances: Paginated<StockBalance>;
    stockRequests: Paginated<StockRequest>;
    stockTransfers?: Paginated<any>;
};

export const WarehouseTab: React.FC<Props> = ({
    subTab,
    warehouses,
    stockBalances,
    stockRequests,
}) => {
    const handleSubTabChange = (newSub: string) => {
        router.get('/product', { tab: 'gudang', sub: newSub }, { preserveState: true });
    };

    const warehouseColumns: DataTableColumn<Warehouse>[] = [
        {
            key: 'code',
            header: 'Kode Gudang',
            render: (w) => <span className="font-mono text-xs font-semibold">{w.code}</span>,
        },
        {
            key: 'name',
            header: 'Nama Gudang',
            render: (w) => <span className="font-semibold text-slate-900">{w.name}</span>,
        },
        {
            key: 'branch',
            header: 'Cabang / Branch',
            render: (w) => w.branch?.name ?? '-',
        },
        {
            key: 'type',
            header: 'Tipe Gudang',
            render: (w) => <span className="capitalize">{w.warehouse_type}</span>,
        },
        {
            key: 'status',
            header: 'Status',
            render: (w) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        w.is_active
                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20'
                            : 'bg-rose-50 text-rose-700 ring-1 ring-inset ring-rose-600/20'
                    }`}
                >
                    {w.is_active ? 'Aktif' : 'Non-aktif'}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (w) => (
                <div className="flex items-center justify-end gap-2">
                    <Link href={`/warehouse/stock-balances?warehouse_id=${w.id}`}>
                        <Button variant="secondary">
                            Lihat Stok
                        </Button>
                    </Link>
                    <Link href={`/warehouse/warehouses/${w.id}/edit`}>
                        <Button variant="secondary">
                            Edit
                        </Button>
                    </Link>
                </div>
            ),
        },
    ];

    const balanceColumns: DataTableColumn<StockBalance>[] = [
        {
            key: 'product',
            header: 'Varian / Produk',
            render: (b) => (
                <div>
                    <div className="font-semibold text-slate-900">
                        {b.product_variant?.product?.name ?? 'Produk'} - {b.product_variant?.variant_name}
                    </div>
                    <div className="text-xs text-slate-500">SKU: {b.product_variant?.sku}</div>
                </div>
            ),
        },
        {
            key: 'warehouse',
            header: 'Gudang',
            render: (b) => b.warehouse?.name ?? '-',
        },
        {
            key: 'qty_on_hand',
            header: 'Qty Tersedia (On Hand)',
            align: 'right',
            render: (b) => (
                <span className={`font-bold ${b.qty_on_hand > 0 ? 'text-emerald-700' : 'text-rose-600'}`}>
                    {b.qty_on_hand}
                </span>
            ),
        },
    ];

    const requestColumns: DataTableColumn<StockRequest>[] = [
        {
            key: 'id',
            header: 'ID',
            render: (r) => `#${r.id}`,
        },
        {
            key: 'requested_at',
            header: 'Tgl Pengajuan',
            render: (r) =>
                r.requested_at
                    ? new Date(r.requested_at).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                      })
                    : '-',
        },
        {
            key: 'requesting',
            header: 'Gudang Peminta',
            render: (r) => r.requesting_warehouse?.name ?? '-',
        },
        {
            key: 'destination',
            header: 'Gudang Tujuan (HQ)',
            render: (r) => r.destination_warehouse?.name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (r) => (
                <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20">
                    {r.status.toUpperCase()}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (r) => (
                <Link href={`/warehouse/stock-requests/${r.id}`}>
                    <Button variant="primary">
                        {r.status === 'pending' ? 'Proses Approval' : 'Detail'}
                    </Button>
                </Link>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {/* Sub Tabs Navigation */}
            <div className="flex items-center gap-2 border-b border-slate-200 pb-3">
                <button
                    type="button"
                    onClick={() => handleSubTabChange('warehouses')}
                    className={`px-3 py-1.5 text-sm font-semibold rounded-lg transition-all ${
                        subTab === 'warehouses'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Daftar Gudang ({warehouses.length})
                </button>
                <button
                    type="button"
                    onClick={() => handleSubTabChange('balances')}
                    className={`px-3 py-1.5 text-sm font-semibold rounded-lg transition-all ${
                        subTab === 'balances'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Saldo Stok ({stockBalances.total})
                </button>
                <button
                    type="button"
                    onClick={() => handleSubTabChange('requests')}
                    className={`px-3 py-1.5 text-sm font-semibold rounded-lg transition-all ${
                        subTab === 'requests'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Permintaan Stok ({stockRequests.total})
                </button>
                <Link
                    href="/warehouse/stock-transfers"
                    className="px-3 py-1.5 text-sm font-semibold rounded-lg text-slate-600 hover:bg-slate-100 transition-all"
                >
                    Transfer Stok
                </Link>
            </div>

            {/* Sub Tab Content */}
            {subTab === 'warehouses' && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/warehouse/warehouses/create">
                            <Button variant="primary">+ Tambah Gudang</Button>
                        </Link>
                    </div>
                    <DataTable<Warehouse>
                        columns={warehouseColumns}
                        rows={warehouses}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada gudang terdaftar."
                    />
                </div>
            )}

            {subTab === 'balances' && (
                <DataTable<StockBalance>
                    columns={balanceColumns}
                    rows={stockBalances.data}
                    getRowKey={(row) => row.id}
                    emptyMessage="Belum ada data saldo stok."
                />
            )}

            {subTab === 'requests' && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/warehouse/stock-requests/create">
                            <Button variant="primary">+ Buat Permintaan Stok</Button>
                        </Link>
                    </div>
                    <DataTable<StockRequest>
                        columns={requestColumns}
                        rows={stockRequests.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada permintaan stok."
                    />
                </div>
            )}
        </div>
    );
};
