import React, { useState } from 'react';
import { Link, router } from '@inertiajs/react';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';
import Button from '@/components/ui/button';
import Modal from '@/components/ui/modal';

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
        id: number;
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

type StockAdjustment = {
    id: number;
    adjustment_number: string;
    type: 'in' | 'out';
    status: 'draft' | 'posted';
    created_at: string;
    warehouse?: Warehouse;
};

type StockTransfer = {
    id: number;
    status: 'draft' | 'shipped' | 'received' | 'cancelled';
    shipped_at?: string;
    received_at?: string;
    created_at: string;
    from_warehouse?: Warehouse;
    to_warehouse?: Warehouse;
};

type StockLayer = {
    id: number;
    qty_remaining: number;
    unit_cost: number;
    received_at: string;
    source_type: string;
    source_id: number;
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
    stockAdjustments?: Paginated<StockAdjustment>;
    stockTransfers?: Paginated<StockTransfer>;
    filters?: Record<string, any>;
};

export const WarehouseTab: React.FC<Props> = ({
    subTab,
    warehouses,
    stockBalances,
    stockRequests,
    stockAdjustments,
    stockTransfers,
    filters = {},
}) => {
    const [selectedBalanceForLayers, setSelectedBalanceForLayers] =
        useState<StockBalance | null>(null);
    const [layersLoading, setLayersLoading] = useState(false);
    const [layersData, setLayersData] = useState<StockLayer[]>([]);

    const handleSubTabChange = (newSub: string) => {
        router.get(
            '/product',
            { tab: 'gudang', sub: newSub },
            { preserveState: true },
        );
    };

    const handleFilterChange = (key: string, value: string) => {
        router.get(
            '/product',
            { tab: 'gudang', sub: subTab, ...filters, [key]: value },
            { preserveState: true },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            '/product',
            { tab: 'gudang', sub: subTab, ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const openLayerModal = async (balance: StockBalance) => {
        setSelectedBalanceForLayers(balance);
        setLayersLoading(true);
        setLayersData([]);
        try {
            const res = await fetch(
                `/warehouse/stock-layers/${balance.warehouse_id}/${balance.product_variant_id}`,
            );
            const data = await res.json();
            setLayersData(data.layers ?? []);
        } catch (e) {
            setLayersData([]);
        } finally {
            setLayersLoading(false);
        }
    };

    const warehouseColumns: DataTableColumn<Warehouse>[] = [
        {
            key: 'code',
            header: 'Kode Gudang',
            render: (w) => (
                <span className="font-mono text-xs font-semibold">
                    {w.code}
                </span>
            ),
        },
        {
            key: 'name',
            header: 'Nama Gudang',
            render: (w) => (
                <span className="font-semibold text-slate-900">{w.name}</span>
            ),
        },
        {
            key: 'branch',
            header: 'Cabang / Branch',
            render: (w) => w.branch?.name ?? '-',
        },
        {
            key: 'type',
            header: 'Tipe Gudang',
            render: (w) => (
                <span className="capitalize">{w.warehouse_type}</span>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            render: (w) => (
                <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                        w.is_active
                            ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20 ring-inset'
                            : 'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 ring-inset'
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
                    <Link
                        href={`/warehouse/stock-balances?warehouse_id=${w.id}`}
                    >
                        <Button variant="secondary">Lihat Stok</Button>
                    </Link>
                    <Link href={`/warehouse/warehouses/${w.id}/edit`}>
                        <Button variant="secondary">Edit</Button>
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
                        {b.product_variant?.product?.name ?? 'Produk'} -{' '}
                        {b.product_variant?.variant_name}
                    </div>
                    <div className="text-xs text-slate-500">
                        SKU: {b.product_variant?.sku}
                    </div>
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
                <span
                    className={`font-bold ${Number(b.qty_on_hand) > 0 ? 'text-emerald-700' : 'text-rose-600'}`}
                >
                    {b.qty_on_hand}
                </span>
            ),
        },
        {
            key: 'actions',
            header: 'Aksi / Inspeksi',
            align: 'right',
            render: (b) => (
                <div className="flex items-center justify-end gap-2">
                    <button
                        type="button"
                        onClick={() => openLayerModal(b)}
                        className="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-600 hover:text-indigo-900"
                    >
                        Layer FIFO
                    </button>
                    <Link
                        href={`/warehouse/stock-movements?product_variant_id=${b.product_variant_id}&warehouse_id=${b.warehouse_id}`}
                        className="inline-flex items-center rounded-md bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:text-slate-900"
                    >
                        Kartu Stok &rarr;
                    </Link>
                </div>
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
                <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 ring-inset">
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

    const adjustmentColumns: DataTableColumn<StockAdjustment>[] = [
        {
            key: 'adjustment_number',
            header: 'No. Adjustment',
            render: (a) => (
                <span className="font-mono text-xs font-bold text-slate-900">
                    {a.adjustment_number}
                </span>
            ),
        },
        {
            key: 'type',
            header: 'Tipe',
            render: (a) =>
                a.type === 'in' ? (
                    <span className="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 ring-inset">
                        STOK MASUK (IN)
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 ring-inset">
                        STOK KELUAR (OUT)
                    </span>
                ),
        },
        {
            key: 'warehouse',
            header: 'Gudang',
            render: (a) => a.warehouse?.name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (a) =>
                a.status === 'posted' ? (
                    <span className="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-blue-700/10 ring-inset">
                        POSTED
                    </span>
                ) : (
                    <span className="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                        DRAFT
                    </span>
                ),
        },
        {
            key: 'created_at',
            header: 'Tanggal Dibuat',
            render: (a) =>
                a.created_at
                    ? new Date(a.created_at).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                      })
                    : '-',
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (a) => (
                <Link href={`/warehouse/adjustments/${a.id}`}>
                    <Button variant="secondary">Detail &rarr;</Button>
                </Link>
            ),
        },
    ];

    const transferColumns: DataTableColumn<StockTransfer>[] = [
        {
            key: 'id',
            header: 'ID Transfer',
            render: (t) => (
                <span className="font-mono text-xs font-bold text-slate-900">
                    #{t.id}
                </span>
            ),
        },
        {
            key: 'from',
            header: 'Gudang Asal',
            render: (t) => t.from_warehouse?.name ?? '-',
        },
        {
            key: 'to',
            header: 'Gudang Tujuan',
            render: (t) => t.to_warehouse?.name ?? '-',
        },
        {
            key: 'status',
            header: 'Status',
            render: (t) => {
                const badges: Record<
                    string,
                    { label: string; className: string }
                > = {
                    draft: {
                        label: 'DRAFT',
                        className: 'bg-slate-100 text-slate-700',
                    },
                    shipped: {
                        label: 'SHIPPED',
                        className:
                            'bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/20',
                    },
                    received: {
                        label: 'RECEIVED',
                        className:
                            'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20',
                    },
                    cancelled: {
                        label: 'CANCELLED',
                        className:
                            'bg-rose-50 text-rose-700 ring-1 ring-rose-600/20',
                    },
                };
                const info = badges[t.status] ?? {
                    label: t.status,
                    className: 'bg-slate-100 text-slate-700',
                };
                return (
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ${info.className}`}
                    >
                        {info.label}
                    </span>
                );
            },
        },
        {
            key: 'created_at',
            header: 'Tgl Buat',
            render: (t) =>
                t.created_at
                    ? new Date(t.created_at).toLocaleDateString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                      })
                    : '-',
        },
        {
            key: 'actions',
            header: 'Aksi',
            align: 'right',
            render: (t) => (
                <Link href={`/warehouse/stock-transfers/${t.id}`}>
                    <Button variant="secondary">Detail &rarr;</Button>
                </Link>
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {/* Sub Tabs Navigation */}
            <div className="flex flex-wrap items-center gap-2 border-b border-slate-200 pb-3">
                <button
                    type="button"
                    onClick={() => handleSubTabChange('warehouses')}
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold transition-all ${
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
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold transition-all ${
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
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold transition-all ${
                        subTab === 'requests'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Permintaan Stok ({stockRequests.total})
                </button>

                <button
                    type="button"
                    onClick={() => handleSubTabChange('adjustments')}
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold transition-all ${
                        subTab === 'adjustments'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Penyesuaian Stok ({stockAdjustments?.total ?? 0})
                </button>

                <button
                    type="button"
                    onClick={() => handleSubTabChange('transfers')}
                    className={`rounded-lg px-3 py-1.5 text-sm font-semibold transition-all ${
                        subTab === 'transfers'
                            ? 'bg-indigo-600 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100'
                    }`}
                >
                    Transfer Stok ({stockTransfers?.total ?? 0})
                </button>

                <Link
                    href="/warehouse/stock-movements"
                    className="ml-auto flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition-all hover:bg-slate-200"
                >
                    <svg
                        className="size-3.5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                        />
                    </svg>
                    <span>Buka Kartu Stok Utama &rarr;</span>
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
                <div className="space-y-3">
                    {/* Filter bar for balances */}
                    <div className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200/80 bg-slate-50 p-3">
                        <input
                            type="text"
                            placeholder="Cari SKU atau nama barang..."
                            defaultValue={filters.search ?? ''}
                            onBlur={(e) =>
                                handleFilterChange('search', e.target.value)
                            }
                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
                        />
                        <select
                            defaultValue={filters.warehouse_id ?? ''}
                            onChange={(e) =>
                                handleFilterChange(
                                    'warehouse_id',
                                    e.target.value,
                                )
                            }
                            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs focus:border-indigo-500 focus:outline-none"
                        >
                            <option value="">Semua Gudang</option>
                            {warehouses.map((w) => (
                                <option key={w.id} value={w.id}>
                                    {w.name} ({w.code})
                                </option>
                            ))}
                        </select>
                    </div>

                    <DataTable<StockBalance>
                        columns={balanceColumns}
                        rows={stockBalances.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada data saldo stok."
                        pagination={{
                            currentPage: stockBalances.current_page,
                            lastPage: stockBalances.last_page,
                            total: stockBalances.total,
                            perPage: stockBalances.per_page,
                        }}
                        onPageChange={handlePageChange}
                    />
                </div>
            )}

            {subTab === 'requests' && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/warehouse/stock-requests/create">
                            <Button variant="primary">
                                + Buat Permintaan Stok
                            </Button>
                        </Link>
                    </div>
                    <DataTable<StockRequest>
                        columns={requestColumns}
                        rows={stockRequests.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada permintaan stok."
                        pagination={{
                            currentPage: stockRequests.current_page,
                            lastPage: stockRequests.last_page,
                            total: stockRequests.total,
                            perPage: stockRequests.per_page,
                        }}
                        onPageChange={handlePageChange}
                    />
                </div>
            )}

            {subTab === 'adjustments' && stockAdjustments && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/warehouse/adjustments/create">
                            <Button variant="primary">
                                + Buat Penyesuaian Stok
                            </Button>
                        </Link>
                    </div>
                    <DataTable<StockAdjustment>
                        columns={adjustmentColumns}
                        rows={stockAdjustments.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada pencatatan penyesuaian stok."
                        pagination={{
                            currentPage: stockAdjustments.current_page,
                            lastPage: stockAdjustments.last_page,
                            total: stockAdjustments.total,
                            perPage: stockAdjustments.per_page,
                        }}
                        onPageChange={handlePageChange}
                    />
                </div>
            )}

            {subTab === 'transfers' && stockTransfers && (
                <div className="space-y-3">
                    <div className="flex justify-end">
                        <Link href="/warehouse/stock-requests/create">
                            <Button variant="primary">
                                + Buat Transfer Stok (Request)
                            </Button>
                        </Link>
                    </div>
                    <DataTable<StockTransfer>
                        columns={transferColumns}
                        rows={stockTransfers.data}
                        getRowKey={(row) => row.id}
                        emptyMessage="Belum ada transfer stok terdaftar."
                        pagination={{
                            currentPage: stockTransfers.current_page,
                            lastPage: stockTransfers.last_page,
                            total: stockTransfers.total,
                            perPage: stockTransfers.per_page,
                        }}
                        onPageChange={handlePageChange}
                    />
                </div>
            )}

            {/* FIFO Layer Inspection Modal */}
            {selectedBalanceForLayers && (
                <Modal
                    title={`Inspeksi Layer FIFO Costing`}
                    onClose={() => setSelectedBalanceForLayers(null)}
                >
                    <div className="space-y-4">
                        <div className="space-y-1 rounded-lg bg-slate-50 p-3 text-xs text-slate-700">
                            <div>
                                <span className="font-semibold">
                                    Varian Produk:
                                </span>{' '}
                                {
                                    selectedBalanceForLayers.product_variant
                                        ?.product?.name
                                }{' '}
                                -{' '}
                                {
                                    selectedBalanceForLayers.product_variant
                                        ?.variant_name
                                }
                            </div>
                            <div>
                                <span className="font-semibold">SKU:</span>{' '}
                                {selectedBalanceForLayers.product_variant?.sku}
                            </div>
                            <div>
                                <span className="font-semibold">Gudang:</span>{' '}
                                {selectedBalanceForLayers.warehouse?.name}
                            </div>
                            <div>
                                <span className="font-semibold">
                                    Total On Hand:
                                </span>{' '}
                                <span className="font-bold text-emerald-700">
                                    {selectedBalanceForLayers.qty_on_hand}
                                </span>
                            </div>
                        </div>

                        <div className="space-y-2">
                            <h4 className="text-xs font-bold tracking-wider text-slate-500 uppercase">
                                Layer FIFO Aktif (Stok Belum Terpakai)
                            </h4>

                            {layersLoading ? (
                                <div className="py-6 text-center text-xs text-slate-500">
                                    Memuat data layer FIFO...
                                </div>
                            ) : layersData.length === 0 ? (
                                <div className="py-6 text-center text-xs text-slate-500">
                                    Tidak ada layer FIFO aktif (stok 0 atau
                                    belum ada pergerakan layer).
                                </div>
                            ) : (
                                <div className="divide-y divide-slate-100 overflow-hidden rounded-lg border border-slate-200 text-xs">
                                    {layersData.map((layer) => (
                                        <div
                                            key={layer.id}
                                            className="flex items-center justify-between bg-white p-3 hover:bg-slate-50/50"
                                        >
                                            <div>
                                                <div className="font-bold text-slate-900">
                                                    Layer #{layer.id}{' '}
                                                    <span className="font-normal text-slate-500">
                                                        (
                                                        {layer.source_type ??
                                                            'pembelian'}
                                                        )
                                                    </span>
                                                </div>
                                                <div className="text-[11px] text-slate-500">
                                                    Diterima:{' '}
                                                    {layer.received_at
                                                        ? new Date(
                                                              layer.received_at,
                                                          ).toLocaleString(
                                                              'id-ID',
                                                          )
                                                        : '-'}
                                                </div>
                                            </div>

                                            <div className="text-right">
                                                <div className="font-bold text-indigo-600">
                                                    Rp{' '}
                                                    {Number(
                                                        layer.unit_cost,
                                                    ).toLocaleString(
                                                        'id-ID',
                                                    )}{' '}
                                                    / unit
                                                </div>
                                                <div className="font-semibold text-slate-700">
                                                    Sisa Qty:{' '}
                                                    {layer.qty_remaining}
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="flex justify-end border-t border-slate-100 pt-3">
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    setSelectedBalanceForLayers(null)
                                }
                            >
                                Tutup
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </div>
    );
};
