import React, { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';
import PageHeader from '@/components/ui/page-header';
import Button from '@/components/ui/button';
import Modal from '@/components/ui/modal';
import InputError from '@/components/input-error';
import type { StockTransfer } from './types';

type Props = {
    stockTransfer: StockTransfer;
};

export default function StockTransferShow({ stockTransfer }: Props) {
    const { errors } = usePage().props;
    const [isShipping, setIsShipping] = useState(false);
    const [showConfirmModal, setShowConfirmModal] = useState(false);

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
            }
        );
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
                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
                        <div className="text-xs font-medium text-slate-500 uppercase">
                            Informasi Transfer
                        </div>
                        <div className="text-sm font-semibold text-slate-900">
                            Status:{' '}
                            <span
                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${
                                    statusBadges[stockTransfer.status]?.className
                                }`}
                            >
                                {statusBadges[stockTransfer.status]?.label}
                            </span>
                        </div>
                        {stockTransfer.stock_request_id && (
                            <div className="text-xs text-slate-600">
                                Berdasarkan Stock Request: #{stockTransfer.stock_request_id}
                            </div>
                        )}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
                        <div className="text-xs font-medium text-slate-500 uppercase">
                            Gudang Asal (Pengirim)
                        </div>
                        <div className="text-sm font-bold text-slate-900">
                            {stockTransfer.from_warehouse?.name ?? '-'}
                        </div>
                        <div className="text-xs text-slate-500">
                            Cabang: {stockTransfer.from_warehouse?.branch?.name ?? '-'}
                        </div>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm space-y-2">
                        <div className="text-xs font-medium text-slate-500 uppercase">
                            Gudang Tujuan (Penerima)
                        </div>
                        <div className="text-sm font-bold text-slate-900">
                            {stockTransfer.to_warehouse?.name ?? '-'}
                        </div>
                        <div className="text-xs text-slate-500">
                            Cabang: {stockTransfer.to_warehouse?.branch?.name ?? '-'}
                        </div>
                    </div>
                </div>

                {/* Tracking Log */}
                {stockTransfer.shipped_at && (
                    <div className="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 text-xs text-indigo-900 flex items-center justify-between">
                        <div>
                            <span className="font-semibold">Dikirim oleh:</span>{' '}
                            {stockTransfer.shipped_by_user?.name ?? `User #${stockTransfer.shipped_by}`}
                        </div>
                        <div>
                            <span className="font-semibold">Tanggal Kirim:</span>{' '}
                            {new Date(stockTransfer.shipped_at).toLocaleString('id-ID')}
                        </div>
                    </div>
                )}

                {/* Item List & FIFO Breakdown */}
                <div className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                    <h3 className="text-base font-bold text-slate-900">
                        Item Barang Dikirim
                    </h3>

                    <div className="divide-y divide-slate-100 border-t border-b border-slate-200">
                        {stockTransfer.items?.map((item) => (
                            <div key={item.id} className="py-4 space-y-3">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <div className="font-bold text-slate-900">
                                            {item.product_variant?.product?.name} -{' '}
                                            {item.product_variant?.variant_name}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            SKU: {item.product_variant?.sku}
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <div className="text-xs text-slate-500">Jumlah Qty</div>
                                        <div className="text-lg font-bold text-indigo-600">
                                            {item.qty}
                                        </div>
                                    </div>
                                </div>

                                {/* FIFO Layers breakdown if shipped */}
                                {item.layers && item.layers.length > 0 && (
                                    <div className="mt-2 rounded-lg bg-slate-50 p-3 border border-slate-200/80 text-xs space-y-1.5">
                                        <div className="font-semibold text-slate-700">
                                            Rincian FIFO Costing Layer (Stok Dikonsumsi):
                                        </div>
                                        <div className="space-y-1">
                                            {item.layers.map((l) => (
                                                <div
                                                    key={l.id}
                                                    className="flex items-center justify-between text-slate-600 bg-white px-2.5 py-1 rounded border border-slate-100"
                                                >
                                                    <span>
                                                        Layer #{l.stock_layer_id} (Cost:{' '}
                                                        Rp{' '}
                                                        {Number(l.unit_cost).toLocaleString('id-ID')}
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
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Confirmation Modal */}
            <Modal show={showConfirmModal} onClose={() => setShowConfirmModal(false)}>
                <div className="p-6 space-y-4">
                    <h3 className="text-lg font-bold text-slate-900">
                        Konfirmasi Pengiriman Stok
                    </h3>
                    <p className="text-sm text-slate-600">
                        Apakah Anda yakin ingin memproses pengiriman stock transfer ini?
                        Stok di gudang asal akan langsung berkurang menggunakan perhitungan FIFO Costing.
                    </p>

                    <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
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
                            {isShipping ? 'Memproses...' : 'Ya, Kirim Sekarang'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </CompanyLayout>
    );
}
