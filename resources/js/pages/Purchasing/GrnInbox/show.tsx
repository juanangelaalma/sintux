import { Button } from '@heroui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PurchaseConfirmDialog from '@/components/purchasing/purchase-confirm-dialog';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatDate, formatQty } from '@/lib/format';
import { GoodsReceiptStatus } from '@/lib/purchasing/status';

type GrnItem = {
    id: number;
    supplier_barcode?: string | null;
    product_name: string;
    sku: string;
    size?: string | null;
    uom_name?: string | null;
    color_raw?: string | null;
    qty_do: number | string;
    qty_received: number | string;
    verification_status: string;
};

type GoodsReceipt = {
    id: number;
    number: string;
    supplier_do_no: string;
    supplier_invoice_no?: string | null;
    po_no?: string | null;
    status: string;
    driver?: string | null;
    nopol?: string | null;
    do_date?: string | null;
    rejection_reason?: string | null;
    transferred_at?: string | null;
    transfer_error?: string | null;
    items: GrnItem[];
};

type Props = {
    goodsReceipt: GoodsReceipt;
};

function ReceiveStatus({ verified }: { verified: boolean }) {
    return verified ? (
        <span className="font-bold text-emerald-600">Verified</span>
    ) : (
        <span className="font-bold text-danger">Not Verified</span>
    );
}

export default function GrnInboxShow({ goodsReceipt }: Props) {
    const [confirmApprove, setConfirmApprove] = useState(false);
    const [showRejectForm, setShowRejectForm] = useState(false);
    const {
        data: rejectData,
        setData: setRejectData,
        post: postReject,
        processing: rejecting,
        errors: rejectErrors,
    } = useForm({ reason: '' });

    const isSubmitted = goodsReceipt.status === GoodsReceiptStatus.Submitted;

    const handleApprove = () => {
        // Tutup dialog dulu: Inertia mempertahankan state lokal saat
        // redirect kembali ke halaman yang sama.
        setConfirmApprove(false);
        router.post(`/purchasing/grn-inbox/${goodsReceipt.id}/approve`, {});
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        postReject(`/purchasing/grn-inbox/${goodsReceipt.id}/reject`, {
            onSuccess: () => setShowRejectForm(false),
        });
    };

    const bundles = new Map<string, GrnItem[]>();

    for (const item of goodsReceipt.items) {
        const key = item.supplier_barcode ?? `item-${item.id}`;
        const group = bundles.get(key) ?? [];
        group.push(item);
        bundles.set(key, group);
    }

    const rows: DetailRow[] = [
        { label: 'No. DO Supplier', value: goodsReceipt.supplier_do_no },
        {
            label: 'No. Faktur Supplier',
            value: goodsReceipt.supplier_invoice_no ?? '-',
        },
        { label: 'No. PO', value: goodsReceipt.po_no ?? '-' },
        {
            label: 'Tanggal DO',
            value: goodsReceipt.do_date
                ? formatDate(goodsReceipt.do_date)
                : '-',
        },
        {
            label: 'Transfer ke Cabang',
            value: goodsReceipt.transferred_at
                ? `Selesai ${formatDate(goodsReceipt.transferred_at)}`
                : goodsReceipt.transfer_error
                  ? 'Gagal — lihat Galat Transfer di bawah'
                  : 'Berjalan saat approval (tanpa antre)',
        },
        ...(goodsReceipt.transfer_error && !goodsReceipt.transferred_at
            ? [
                  {
                      label: 'Galat Transfer',
                      value: goodsReceipt.transfer_error,
                  } as DetailRow,
              ]
            : []),
        ...(goodsReceipt.rejection_reason
            ? [
                  {
                      label: 'Alasan Penolakan',
                      value: goodsReceipt.rejection_reason,
                  } as DetailRow,
              ]
            : []),
    ];

    return (
        <CompanyLayout>
            <Head title={`Approval #${goodsReceipt.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Approval GRN"
                    title={`Approval #${goodsReceipt.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/grn-inbox">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            {isSubmitted && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onPress={() => setConfirmApprove(true)}
                                >
                                    Setujui & Transfer
                                </Button>
                            )}
                            {isSubmitted && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onPress={() => setShowRejectForm((v) => !v)}
                                >
                                    Tolak
                                </Button>
                            )}
                        </>
                    }
                />

                {isSubmitted && showRejectForm && (
                    <form
                        onSubmit={handleReject}
                        className="rounded-xl border border-rose-500/40 bg-rose-500/5 p-4"
                    >
                        <label className="block text-xs font-semibold text-foreground">
                            Alasan penolakan (wajib — dibaca cabang saat revisi)
                            *
                        </label>
                        <textarea
                            value={rejectData.reason}
                            onChange={(e) =>
                                setRejectData('reason', e.target.value)
                            }
                            rows={3}
                            placeholder="Contoh: qty fisik tidak cocok dengan DO, foto surat jalan buram..."
                            className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                        />
                        {rejectErrors.reason && (
                            <p className="mt-1 text-xs text-danger">
                                {rejectErrors.reason}
                            </p>
                        )}
                        <div className="mt-3 flex justify-end gap-2">
                            <Button
                                type="button"
                                variant="tertiary"
                                onPress={() => setShowRejectForm(false)}
                            >
                                Batal
                            </Button>
                            <Button
                                type="submit"
                                variant="danger"
                                isDisabled={rejecting}
                            >
                                Kirim Penolakan
                            </Button>
                        </div>
                    </form>
                )}

                <PurchaseDocumentDetail
                    status={goodsReceipt.status}
                    statusLabel="Status"
                    rows={rows}
                >
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-left text-sm text-foreground">
                            <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                <tr>
                                    <th className="px-3 py-3">No</th>
                                    <th className="px-3 py-3">
                                        Receive Status
                                    </th>
                                    <th className="px-3 py-3">Barcode</th>
                                    <th className="px-3 py-3">Item Name</th>
                                    <th className="px-3 py-3">Pkg / Size</th>
                                    <th className="px-3 py-3 text-right">
                                        Qty DO
                                    </th>
                                    <th className="px-3 py-3 text-right">
                                        Qty Diterima
                                    </th>
                                    <th className="px-3 py-3 text-right">
                                        Selisih
                                    </th>
                                    <th className="px-3 py-3">UoM</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {[...bundles.entries()].map(
                                    ([barcode, group], index) => {
                                        const first = group[0];
                                        const verified = group.every(
                                            (item) =>
                                                item.verification_status ===
                                                'verified',
                                        );
                                        const totalDo = group.reduce(
                                            (sum, item) =>
                                                sum + Number(item.qty_do || 0),
                                            0,
                                        );
                                        const totalReceived = group.reduce(
                                            (sum, item) =>
                                                sum +
                                                Number(item.qty_received || 0),
                                            0,
                                        );
                                        const gap = totalDo - totalReceived;

                                        return (
                                            <tr
                                                key={barcode}
                                                className="hover:bg-surface-secondary/60"
                                            >
                                                <td className="px-3 py-3 text-muted">
                                                    {index + 1}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <ReceiveStatus
                                                        verified={verified}
                                                    />
                                                </td>
                                                <td className="px-3 py-3 font-mono text-xs font-semibold">
                                                    {barcode}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <p className="font-semibold">
                                                        {first.product_name}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted">
                                                        {first.sku}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {group
                                                            .map(
                                                                (d) =>
                                                                    `${d.color_raw || '-'} (${formatQty(d.qty_received)})`,
                                                            )
                                                            .join(' · ')}
                                                    </p>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {first.size ?? '-'}
                                                </td>
                                                <td className="px-3 py-3 text-right">
                                                    {formatQty(totalDo)}
                                                </td>
                                                <td className="px-3 py-3 text-right font-bold">
                                                    {formatQty(totalReceived)}
                                                </td>
                                                <td
                                                    className={`px-3 py-3 text-right font-bold ${gap !== 0 ? 'text-danger' : 'text-muted'}`}
                                                >
                                                    {gap === 0
                                                        ? '—'
                                                        : formatQty(gap)}
                                                </td>
                                                <td className="px-3 py-3">
                                                    {first.uom_name ?? '-'}
                                                </td>
                                            </tr>
                                        );
                                    },
                                )}
                            </tbody>
                        </table>
                    </div>
                </PurchaseDocumentDetail>
            </div>

            <PurchaseConfirmDialog
                open={confirmApprove}
                title="Setujui GRN?"
                description="Stok masuk gudang HO lalu otomatis ditransfer ke cabang tujuan dan tercatat sebagai stock transfer."
                confirmLabel="Setujui"
                onConfirm={handleApprove}
                onClose={() => setConfirmApprove(false)}
            />
        </CompanyLayout>
    );
}
