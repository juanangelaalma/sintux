import React from 'react';
import { Head, router } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';
import PageHeader from '@/components/ui/page-header';
import DataTable from '@/components/tables/data-table';
import type { DataTableColumn } from '@/components/tables/data-table';

type StockMovement = {
    id: number;
    movement_type: string;
    qty: number;
    unit_cost: number;
    created_at: string;
    warehouse?: { id: number; name: string };
    product_variant?: {
        id: number;
        sku: string;
        variant_name: string;
        product?: { name: string; uom?: { name: string } };
    };
    stock_layer_id?: number;
    reference_type?: string;
    reference_id?: number;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
};

type Props = {
    stockMovements: Paginated<StockMovement>;
    filters: {
        search?: string;
        warehouse_id?: number;
        movement_type?: string;
        date_from?: string;
        date_to?: string;
    };
};

export default function StockMovementsIndex({
    stockMovements,
    filters,
}: Props) {
    const handleFilterChange = (key: string, value: string) => {
        router.get(
            '/warehouse/stock-movements',
            { ...filters, [key]: value, page: 1 },
            { preserveState: true },
        );
    };

    const changePage = (page: number) => {
        router.get(
            '/warehouse/stock-movements',
            { ...filters, page },
            { preserveState: true, preserveScroll: true },
        );
    };

    const movementBadges: Record<string, { label: string; className: string }> =
        {
            transfer_in: {
                label: 'TRANSFER IN (+)',
                className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
            },
            transfer_out: {
                label: 'TRANSFER OUT (-)',
                className: 'bg-rose-50 text-rose-700 ring-rose-600/20',
            },
            adjustment_in: {
                label: 'ADJUSTMENT IN (+)',
                className: 'bg-blue-50 text-blue-700 ring-blue-600/20',
            },
            adjustment_out: {
                label: 'ADJUSTMENT OUT (-)',
                className: 'bg-amber-50 text-amber-700 ring-amber-600/20',
            },
        };

    const columns: DataTableColumn<StockMovement>[] = [
        {
            key: 'created_at',
            header: 'Tanggal & Waktu',
            render: (m) =>
                m.created_at
                    ? new Date(m.created_at).toLocaleString('id-ID', {
                          day: '2-digit',
                          month: 'short',
                          year: 'numeric',
                          hour: '2-digit',
                          minute: '2-digit',
                      })
                    : '-',
        },
        {
            key: 'product',
            header: 'Varian / Produk',
            render: (m) => (
                <div>
                    <div className="font-semibold text-slate-900">
                        {m.product_variant?.product?.name ?? 'Produk'} -{' '}
                        {m.product_variant?.variant_name}
                    </div>
                    <div className="text-xs text-slate-500">
                        SKU: {m.product_variant?.sku}
                    </div>
                </div>
            ),
        },
        {
            key: 'warehouse',
            header: 'Gudang',
            render: (m) => m.warehouse?.name ?? '-',
        },
        {
            key: 'type',
            header: 'Tipe Pergerakan',
            render: (m) => {
                const info = movementBadges[m.movement_type] ?? {
                    label: m.movement_type.toUpperCase(),
                    className: 'bg-slate-100 text-slate-700',
                };
                return (
                    <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${info.className}`}
                    >
                        {info.label}
                    </span>
                );
            },
        },
        {
            key: 'qty',
            header: 'Jumlah Qty',
            align: 'right',
            render: (m) => (
                <span
                    className={`font-bold ${
                        Number(m.qty) > 0 ? 'text-emerald-700' : 'text-rose-600'
                    }`}
                >
                    {Number(m.qty) > 0 ? `+${m.qty}` : m.qty}
                </span>
            ),
        },
        {
            key: 'unit_cost',
            header: 'HPP (Unit Cost)',
            align: 'right',
            render: (m) =>
                m.unit_cost
                    ? `Rp ${Number(m.unit_cost).toLocaleString('id-ID')}`
                    : '-',
        },
        {
            key: 'layer',
            header: 'Ref Layer',
            render: (m) =>
                m.stock_layer_id ? (
                    <span className="font-mono text-xs text-slate-600">
                        Layer #{m.stock_layer_id}
                    </span>
                ) : (
                    '-'
                ),
        },
    ];

    return (
        <CompanyLayout>
            <Head title="Kartu Stok (Pergerakan Barang)" />

            <div className="space-y-6">
                <PageHeader
                    title="Kartu Stok (Stock Movements)"
                    description="Audit trail riwayat keluar-masuk barang, transfer stok, dan penyesuaian HPP secara real-time."
                />

                {/* Filter Bar */}
                <div className="flex flex-wrap items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <input
                        type="text"
                        placeholder="Cari SKU, Nama Produk, Gudang..."
                        defaultValue={filters.search ?? ''}
                        onBlur={(e) =>
                            handleFilterChange('search', e.target.value)
                        }
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                    />

                    <select
                        defaultValue={filters.movement_type ?? ''}
                        onChange={(e) =>
                            handleFilterChange('movement_type', e.target.value)
                        }
                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                    >
                        <option value="">Semua Tipe Pergerakan</option>
                        <option value="transfer_in">Transfer Masuk (+)</option>
                        <option value="transfer_out">
                            Transfer Keluar (-)
                        </option>
                        <option value="adjustment_in">
                            Adjustment Masuk (+)
                        </option>
                        <option value="adjustment_out">
                            Adjustment Keluar (-)
                        </option>
                    </select>

                    <div className="flex items-center gap-2 text-xs text-slate-500">
                        <span>Dari:</span>
                        <input
                            type="date"
                            defaultValue={filters.date_from ?? ''}
                            onChange={(e) =>
                                handleFilterChange('date_from', e.target.value)
                            }
                            className="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                        />
                        <span>Sampai:</span>
                        <input
                            type="date"
                            defaultValue={filters.date_to ?? ''}
                            onChange={(e) =>
                                handleFilterChange('date_to', e.target.value)
                            }
                            className="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs"
                        />
                    </div>
                </div>

                <DataTable<StockMovement>
                    columns={columns}
                    rows={stockMovements.data}
                    getRowKey={(row) => row.id}
                    emptyMessage="Belum ada catatan pergerakan stok."
                    pagination={{
                        currentPage: stockMovements.current_page,
                        lastPage: stockMovements.last_page,
                        total: stockMovements.total,
                        perPage: stockMovements.per_page,
                    }}
                    onPageChange={changePage}
                />
            </div>
        </CompanyLayout>
    );
}
