import { Head, Link, router, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import InputError from '@/components/input-error';
import Button from '@/components/ui/button';
import Modal from '@/components/ui/modal';
import PageHeader from '@/components/ui/page-header';
import CompanyLayout from '@/layouts/company/company-layout';
import type { StockTransfer, StockTransferItem } from './types';

type Props = {
    stockTransfer: StockTransfer;
};

type ReceiveItem = {
    stock_transfer_item_id: number;
    qty_received: number;
};

export default function StockTransferShow({ stockTransfer }: Props) {
    const { errors } = usePage().props;
    const [isShipping, setIsShipping] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);
    const [isReceiving, setIsReceiving] = useState(false);
    const [showReceiveModal, setShowReceiveModal] = useState(false);
    const [receiveItems, setReceiveItems] = useState<ReceiveItem[]>(
        stockTransfer.items?.map((item) => ({
            stock_transfer_item_id: item.id,
            qty_received:
                (item.qty_shipped ?? item.qty) - (item.qty_received ?? 0),
        })) ?? [],
    );

    const handleShip = () => {
        setIsShipping(true);
        router.post(
            `/warehouse/stock-transfers/${stockTransfer.id}/ship`,
            {},
            {
                onFinish: () => {
                    setIsShipping(false);
                    setShowConfirmModal(false);
                },
            },
        );
    };

    const handleReceive = () => {
        setIsReceiving(true);
        router.post(
            `/warehouse/stock-transfers/${stockTransfer.id}/receive`,
            {
                received_items: receiveItems,
            },
            {
                onFinish: () => {
                    setIsReceiving(false);
                    setShowReceiveModal(false);
                },
            },
        );
    };

    const updateReceiveQty = (itemId: number, qty: number) => {
        setReceiveItems((prev) =>
            prev.map((item) =>
                item.stock_transfer_item_id === itemId
                    ? { ...item, qty_received: qty }
                    : item,
            ),
        );
    };

    const getRemainingQty = (item: StockTransferItem) => {
        const shipped = item.qty_shipped ?? item.qty;
        const received = item.qty_received ?? 0;

        return shipped - received;
    };

    const statusBadges: Record<string, { label: string; className: string }> = {
        draft: {
            label: 'DRAFT',
            className: 'bg-slate-100 text-slate-700 ring-slate-600/20',
        },
        shipped: {
            label: 'SHIPPED (DALAM PERJALANAN)',
            className: 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        },
        received: {
            label: 'RECEIVED (SELESAI)',
            className: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        },
        cancelled: {
            label: 'DIBATALKAN',
            className: 'bg-rose-50 text-rose-700 ring-rose-600/20',
        },
    };

    return (
        <CompanyLayout>
            <Head title={`Detail Transfer #${stockTransfer.id}`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Transfer Stok #${stockTransfer.id}`}
                    description="Detail barang dan status pengiriman stok antar gudang."
                    actions={
                        <div className="flex items-center gap-3">
                            <Link href="/product?tab=gudang&sub=requests">
                                <Button variant="secondary">Kembali</Button>
                            </Link>
                            {stockTransfer.status === 'draft' && (
                                <Button
                                    variant="primary"
                                    onClick={() => setShowConfirmModal(true)}
                                >
                                    Kirim Stock Transfer
                                </Button>
                            )}
                            {stockTransfer.status === 'shipped' && (
                                <Button
                                    variant="primary"
                                    onClick={() => setShowReceiveModal(true)}
                                >
                                    Terima Stock Transfer
                                </Button>
                            )}
                        </div>
                    }
                />

                {errors.stock_transfer && (
                    <div className="rounded-lg bg-rose-50 p-4 ring-1 ring-rose-300">
                        <InputError message={errors.stock_transfer as string} />
                    </div>
                )}

                {/* Header Information */}
                <div className="grid grid-cols-1 gap-6 md:grid-cols-3">
                    <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase">
                            Informasi Transfer
                        </div>
                        <div className="text-sm font-semibold text-slate-900">
                            Status:{' '}
                            <span
                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                                    statusBadges[stockTransfer.status]
                                        ?.className
                                }`}
                            >
                                {statusBadges[stockTransfer.status]?.label}
                            </span>
                        </div>
                        {stockTransfer.stock_request_id && (
                            <div className="text-xs text-slate-600">
                                Berdasarkan Stock Request: #
                                {stockTransfer.stock_request_id}
                            </div>
                        )}
                    </div>

                    <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase">
                            Gudang Asal (Pengirim)
                        </div>
                        <div className="text-sm font-bold text-slate-900">
                            {stockTransfer.from_warehouse?.name ?? '-'}
                        </div>
                        <div className="text-xs text-slate-500">
                            Cabang:{' '}
                            {stockTransfer.from_warehouse?.branch?.name ?? '-'}
                        </div>
                    </div>

                    <div className="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="text-xs font-medium text-slate-500 uppercase">
                            Gudang Tujuan (Penerima)
                        </div>
                        <div className="text-sm font-bold text-slate-900">
                            {stockTransfer.to_warehouse?.name ?? '-'}
                        </div>
                        <div className="text-xs text-slate-500">
                            Cabang:{' '}
                            {stockTransfer.to_warehouse?.branch?.name ?? '-'}
                        </div>
                    </div>
                </div>

                {/* Tracking Log */}
                {stockTransfer.shipped_at && (
                    <div className="flex items-center justify-between rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 text-xs text-indigo-900">
                        <div>
                            <span className="font-semibold">Dikirim oleh:</span>{' '}
                            {stockTransfer.shipped_by_user?.name ??
                                `User #${stockTransfer.shipped_by}`}
                        </div>
                        <div>
                            <span className="font-semibold">
                                Tanggal Kirim:
                            </span>{' '}
                            {new Date(stockTransfer.shipped_at).toLocaleString(
                                'id-ID',
                            )}
                        </div>
                    </div>
                )}

                {stockTransfer.received_at && (
                    <div className="flex items-center justify-between rounded-xl border border-emerald-100 bg-emerald-50/50 p-4 text-xs text-emerald-900">
                        <div>
                            <span className="font-semibold">
                                Diterima oleh:
                            </span>{' '}
                            {stockTransfer.received_by_user?.name ??
                                `User #${stockTransfer.received_by}`}
                        </div>
                        <div>
                            <span className="font-semibold">
                                Tanggal Terima:
                            </span>{' '}
                            {new Date(stockTransfer.received_at).toLocaleString(
                                'id-ID',
                            )}
                        </div>
                    </div>
                )}

                {/* Item List & FIFO Breakdown */}
                <div className="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 className="text-base font-bold text-slate-900">
                        Item Barang Dikirim
                    </h3>

                    <div className="divide-y divide-slate-100 border-t border-b border-slate-200">
                        {stockTransfer.items?.map((item) => (
                            <div key={item.id} className="space-y-3 py-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="font-bold text-slate-900">
                                            {
                                                item.product_variant?.product
                                                    ?.name
                                            }{' '}
                                            -{' '}
                                            {item.product_variant?.variant_name}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            SKU: {item.product_variant?.sku}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        {item.qty_shipped !== null &&
                                        item.qty_shipped !== undefined ? (
                                            <>
                                                <div className="text-xs text-slate-500">
                                                    Dikirim / Diterima / Sisa
                                                </div>
                                                <div className="text-lg font-bold text-indigo-600">
                                                    {item.qty_shipped} /{' '}
                                                    {item.qty_received ?? 0} /{' '}
                                                    {getRemainingQty(item)}
                                                </div>
                                            </>
                                        ) : (
                                            <>
                                                <div className="text-xs text-slate-500">
                                                    Jumlah Qty
                                                </div>
                                                <div className="text-lg font-bold text-indigo-600">
                                                    {item.qty}
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>

                                {/* FIFO Layers breakdown if shipped */}
                                {item.layers && item.layers.length > 0 && (
                                    <div className="mt-2 space-y-1.5 rounded-lg border border-slate-200/80 bg-slate-50 p-3 text-xs">
                                        <div className="font-semibold text-slate-700">
                                            Rincian FIFO Costing Layer (Stok
                                            Dikonsumsi):
                                        </div>
                                        <div className="space-y-1">
                                            {item.layers.map((l) => (
                                                <div
                                                    key={l.id}
                                                    className="flex items-center justify-between rounded border border-slate-100 bg-white px-2.5 py-1 text-slate-600"
                                                >
                                                    <span>
                                                        Layer #
                                                        {l.stock_layer_id}{' '}
                                                        (Cost: Rp{' '}
                                                        {Number(
                                                            l.unit_cost,
                                                        ).toLocaleString(
                                                            'id-ID',
                                                        )}
                                                        )
                                                    </span>
                                                    <span className="font-medium text-slate-900">
                                                        Qty Taken: {l.qty_taken}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {/* Discrepancy display */}
                                {item.discrepancies &&
                                    item.discrepancies.length > 0 && (
                                        <div className="mt-2 space-y-1.5 rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs">
                                            <div className="font-semibold text-amber-700">
                                                Discrepancy:
                                            </div>
                                            {item.discrepancies.map((d) => (
                                                <div
                                                    key={d.id}
                                                    className="rounded border border-amber-200 bg-white px-2.5 py-1 text-amber-800"
                                                >
                                                    <span>
                                                        Dikirim: {d.shipped_qty}{' '}
                                                        / Diterima:{' '}
                                                        {d.received_qty} /
                                                        Selisih:{' '}
                                                        {d.difference_qty}
                                                    </span>
                                                    <span className="ml-2 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium uppercase">
                                                        {d.status}
                                                    </span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Confirmation Modal */}
            {showConfirmModal && (
                <Modal
                    title="Konfirmasi Pengiriman Stok"
                    onClose={() => setShowConfirmModal(false)}
                >
                    <div className="space-y-4">
                        <p className="text-sm text-slate-600">
                            Apakah Anda yakin ingin memproses pengiriman stock
                            transfer ini? Stok di gudang asal akan langsung
                            berkurang menggunakan perhitungan FIFO Costing.
                        </p>

                        <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                            <Button
                                variant="secondary"
                                onClick={() => setShowConfirmModal(false)}
                                disabled={isShipping}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleShip}
                                disabled={isShipping}
                            >
                                {isShipping
                                    ? 'Memproses...'
                                    : 'Ya, Kirim Sekarang'}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}

            {/* Receive Modal */}
            {showReceiveModal && (
                <Modal
                    title="Terima Stock Transfer"
                    onClose={() => setShowReceiveModal(false)}
                >
                    <div className="space-y-4">
                        <p className="text-sm text-slate-600">
                            Masukkan jumlah yang diterima untuk setiap item.
                            Jika ada selisih, sistem akan mencatat sebagai
                            discrepancy.
                        </p>

                        <div className="space-y-3">
                            {stockTransfer.items?.map((item) => {
                                const remaining = getRemainingQty(item);
                                const receiveItem = receiveItems.find(
                                    (r) => r.stock_transfer_item_id === item.id,
                                );

                                return (
                                    <div
                                        key={item.id}
                                        className="rounded-lg border border-slate-200 p-3"
                                    >
                                        <div className="mb-2 text-sm font-medium text-slate-900">
                                            {
                                                item.product_variant?.product
                                                    ?.name
                                            }{' '}
                                            -{' '}
                                            {item.product_variant?.variant_name}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            Dikirim:{' '}
                                            {item.qty_shipped ?? item.qty} |
                                            Sisa: {remaining}
                                        </div>
                                        <div className="mt-2">
                                            <label className="block text-xs font-medium text-slate-700">
                                                Qty Diterima
                                            </label>
                                            <input
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                max={remaining}
                                                value={
                                                    receiveItem?.qty_received ??
                                                    remaining
                                                }
                                                onChange={(e) =>
                                                    updateReceiveQty(
                                                        item.id,
                                                        parseFloat(
                                                            e.target.value,
                                                        ) || 0,
                                                    )
                                                }
                                                className="mt-1 block w-full rounded-md border border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 focus:outline-none"
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>

                        {errors.qty_received && (
                            <InputError
                                message={errors.qty_received as string}
                            />
                        )}

                        <div className="flex justify-end gap-3 border-t border-slate-100 pt-4">
                            <Button
                                variant="secondary"
                                onClick={() => setShowReceiveModal(false)}
                                disabled={isReceiving}
                            >
                                Batal
                            </Button>
                            <Button
                                variant="primary"
                                onClick={handleReceive}
                                disabled={isReceiving}
                            >
                                {isReceiving
                                    ? 'Memproses...'
                                    : 'Ya, Terima Sekarang'}
                            </Button>
                        </div>
                    </div>
                </Modal>
            )}
        </CompanyLayout>
    );
}
