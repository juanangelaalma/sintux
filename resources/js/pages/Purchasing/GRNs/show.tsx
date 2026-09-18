import { Button } from '@heroui/react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PurchaseConfirmDialog from '@/components/purchasing/purchase-confirm-dialog';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatDate, formatQty } from '@/lib/format';
import { GoodsReceiptStatus } from '@/lib/purchasing/status';

type Item = {
    id: number;
    supplier_barcode?: string | null;
    product_variant_id: number | null;
    product_name: string;
    sku: string;
    size?: string | null;
    uom_name?: string | null;
    color_raw?: string | null;
    color?: string | null;
    qty_do: number | string;
    qty_received: number | string;
    qty_invoiced?: number | string | null;
    qty_confirmed: boolean;
    verification_status: string;
    unit_price_supplier?: number | string | null;
};

type GoodsReceipt = {
    id: number;
    number: string;
    supplier_do_no: string;
    supplier_invoice_no?: string | null;
    po_no?: string | null;
    purchase_order_id: number;
    status: string;
    receipt_date: string;
    do_date?: string | null;
    driver?: string | null;
    nopol?: string | null;
    note?: string | null;
    rejection_reason?: string | null;
    transferred_at?: string | null;
    transfer_error?: string | null;
    items: Item[];
    purchase_order?: {
        id: number;
        number: string;
    } | null;
};

type Warehouse = {
    id: number;
    code: string;
    name: string;
};

type Props = {
    goodsReceipt: GoodsReceipt;
    warehouse?: Warehouse | null;
};

function ReceiveStatus({ verified }: { verified: boolean }) {
    return verified ? (
        <span className="font-bold text-emerald-600">Verified</span>
    ) : (
        <span className="font-bold text-danger">Not Verified</span>
    );
}

export default function GoodsReceiptsShow({ goodsReceipt, warehouse }: Props) {
    const { props } = usePage();
    const auth = (props.auth ?? {}) as { is_hq?: boolean };
    const isHq = auth.is_hq ?? false;

    const [barcode, setBarcode] = useState('');
    const [search, setSearch] = useState('');
    const [expanded, setExpanded] = useState<Record<string, boolean>>({});
    const [confirmSubmit, setConfirmSubmit] = useState(false);
    const [confirmApprove, setConfirmApprove] = useState(false);
    const [showRejectForm, setShowRejectForm] = useState(false);
    const {
        data: rejectData,
        setData: setRejectData,
        post: postReject,
        processing: rejecting,
        errors: rejectErrors,
    } = useForm({ reason: '' });

    const isDraft = goodsReceipt.status === GoodsReceiptStatus.Draft;
    const isSubmitted = goodsReceipt.status === GoodsReceiptStatus.Submitted;
    const isApproved = goodsReceipt.status === GoodsReceiptStatus.Approved;
    const isRejected = goodsReceipt.status === GoodsReceiptStatus.Rejected;
    const canWork = !isHq && (isDraft || isRejected);
    const canDecide = isHq && isSubmitted;

    // Kelompokkan baris warna per bundle barcode.
    const bundles = new Map<string, Item[]>();

    for (const item of goodsReceipt.items) {
        const key = item.supplier_barcode ?? `item-${item.id}`;
        const group = bundles.get(key) ?? [];
        group.push(item);
        bundles.set(key, group);
    }

    const bundleStates = [...bundles.entries()].map(([key, group]) => ({
        key,
        group,
        verified: group.every(
            (item) => item.verification_status === 'verified',
        ),
        confirmed: group.every((item) => item.qty_confirmed),
        unmapped: group.filter((item) => item.product_variant_id === null)
            .length,
        totalReceived: group.reduce(
            (sum, item) => sum + Number(item.qty_received || 0),
            0,
        ),
        totalDo: group.reduce((sum, item) => sum + Number(item.qty_do || 0), 0),
    }));

    const allVerified = bundleStates.every((b) => b.verified);
    const allMapped = bundleStates.every((b) => b.unmapped === 0);
    const allConfirmed = bundleStates.every((b) => b.confirmed);
    const canSubmit = isDraft && allVerified && allMapped && allConfirmed;

    const totalReceived = goodsReceipt.items.reduce(
        (sum, item) => sum + Number(item.qty_received || 0),
        0,
    );
    const totalInvoiced = goodsReceipt.items.reduce(
        (sum, item) => sum + Number(item.qty_invoiced || 0),
        0,
    );
    // Strict 1:1 — satu GRN tepat satu faktur.
    const billingStatus =
        totalInvoiced <= 0 ? 'Belum Difaktur' : 'Sudah Difaktur Penuh';

    const keyword = search.trim().toLowerCase();
    const visibleBundles =
        keyword === ''
            ? bundleStates
            : bundleStates.filter(
                  (b) =>
                      b.key.toLowerCase().includes(keyword) ||
                      b.group.some(
                          (item) =>
                              item.product_name
                                  .toLowerCase()
                                  .includes(keyword) ||
                              item.sku.toLowerCase().includes(keyword) ||
                              (item.color_raw ?? '')
                                  .toLowerCase()
                                  .includes(keyword),
                      ),
              );

    const toggleExpand = (key: string) => {
        setExpanded((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const handleScan = (e: React.FormEvent) => {
        e.preventDefault();

        if (barcode.trim() === '') {
            return;
        }

        router.post(
            `/purchasing/grns/${goodsReceipt.id}/verify`,
            { barcode: barcode.trim() },
            {
                preserveScroll: true,
                onSuccess: () => setBarcode(''),
            },
        );
    };

    const handleQty = (itemId: number, qty: string) => {
        router.post(
            `/purchasing/grn-items/${itemId}/qty`,
            { qty_received: qty },
            { preserveScroll: true },
        );
    };

    const handleConfirm = (itemId: number, confirmed: boolean) => {
        router.post(
            `/purchasing/grn-items/${itemId}/confirm`,
            { confirmed },
            { preserveScroll: true },
        );
    };

    const handleSubmit = () => {
        // Tutup dialog dulu: Inertia mempertahankan state lokal saat
        // redirect kembali ke halaman yang sama.
        setConfirmSubmit(false);
        router.post(`/purchasing/grns/${goodsReceipt.id}/submit`, {});
    };

    const handleRevise = () => {
        router.post(`/purchasing/grns/${goodsReceipt.id}/revise`, {});
    };

    const handleApprove = () => {
        setConfirmApprove(false);
        router.post(`/purchasing/grn-inbox/${goodsReceipt.id}/approve`, {});
    };

    const handleReject = (e: React.FormEvent) => {
        e.preventDefault();
        postReject(`/purchasing/grn-inbox/${goodsReceipt.id}/reject`, {
            onSuccess: () => setShowRejectForm(false),
        });
    };

    const rows: DetailRow[] = [
        { label: 'No. DO Supplier', value: goodsReceipt.supplier_do_no },
        {
            label: 'No. PO',
            value:
                goodsReceipt.po_no ??
                goodsReceipt.purchase_order?.number ??
                '-',
        },
        {
            label: 'No. Faktur Supplier',
            value: goodsReceipt.supplier_invoice_no ?? '-',
        },
        {
            label: 'Tanggal DO',
            value: goodsReceipt.do_date
                ? formatDate(goodsReceipt.do_date)
                : '-',
        },
        {
            label: 'Tanggal terima',
            value: formatDate(goodsReceipt.receipt_date),
        },
        {
            label: 'Sopir / Nopol',
            value: `${goodsReceipt.driver ?? '-'} / ${goodsReceipt.nopol ?? '-'}`,
        },
        ...(warehouse
            ? [
                  {
                      label: 'Gudang',
                      value: `${warehouse.code} — ${warehouse.name}`,
                  } as DetailRow,
              ]
            : []),
        ...(isApproved
            ? [
                  {
                      label: 'Status Penagihan',
                      value: `${billingStatus} (tertagih ${totalInvoiced} dari ${totalReceived})`,
                  } as DetailRow,
                  {
                      label: 'Transfer ke Cabang',
                      value: goodsReceipt.transferred_at
                          ? `Selesai ${formatDate(goodsReceipt.transferred_at)}`
                          : goodsReceipt.transfer_error
                            ? 'Gagal — lihat Galat Transfer di bawah'
                            : 'Berjalan saat approval (tanpa antre)',
                  } as DetailRow,
              ]
            : []),
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
            <Head title={`GRN #${goodsReceipt.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail GRN"
                    title={`GRN #${goodsReceipt.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/grns">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            {canWork && isDraft && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    isDisabled={!canSubmit}
                                    onPress={() => setConfirmSubmit(true)}
                                >
                                    Kirim ke HO
                                </Button>
                            )}
                            {canWork && isRejected && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onPress={handleRevise}
                                >
                                    Revisi & Kembalikan ke Draft
                                </Button>
                            )}
                            {canDecide && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onPress={() => setConfirmApprove(true)}
                                >
                                    Setujui & Transfer
                                </Button>
                            )}
                            {canDecide && (
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onPress={() => setShowRejectForm((v) => !v)}
                                >
                                    Tolak
                                </Button>
                            )}
                            {isApproved && totalInvoiced <= 0 && isHq && (
                                <Link
                                    href={`/purchasing/invoices/create?goods_receipt_id=${goodsReceipt.id}`}
                                >
                                    <Button type="button" variant="primary">
                                        Buat Faktur
                                    </Button>
                                </Link>
                            )}
                        </>
                    }
                />

                {isRejected && goodsReceipt.rejection_reason && (
                    <div className="rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-700 dark:text-rose-300">
                        Ditolak HO: {goodsReceipt.rejection_reason} — koreksi
                        data di bawah lalu kirim ulang.
                    </div>
                )}

                {canDecide && showRejectForm && (
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
                    statusLabel="Status GRN"
                    rows={rows}
                    note={goodsReceipt.note}
                >
                    {canWork && isDraft && (
                        <form
                            onSubmit={handleScan}
                            className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end"
                        >
                            <div className="flex-1">
                                <label
                                    htmlFor="scan-barcode"
                                    className="block text-xs font-semibold text-foreground"
                                >
                                    Barcode
                                </label>
                                <input
                                    id="scan-barcode"
                                    className="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 font-mono text-sm text-foreground"
                                    placeholder="Scan / ketik barcode lalu Enter..."
                                    value={barcode}
                                    onChange={(e) => setBarcode(e.target.value)}
                                    autoFocus
                                />
                            </div>
                            <div className="sm:min-w-48">
                                <label
                                    htmlFor="search-line"
                                    className="block text-xs font-semibold text-foreground"
                                >
                                    Search
                                </label>
                                <input
                                    id="search-line"
                                    className="mt-1 w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground"
                                    placeholder="Barcode / nama / SKU / warna…"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                />
                            </div>
                            <Button type="submit" variant="primary">
                                Verifikasi
                            </Button>
                        </form>
                    )}

                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Diterima
                        </h3>
                        <div className="overflow-x-auto rounded-lg border border-border">
                            <table className="w-full text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-3 py-3">No</th>
                                        {(canWork || canDecide) && (
                                            <th className="px-3 py-3">
                                                Receive Status
                                            </th>
                                        )}
                                        <th className="px-3 py-3">Barcode</th>
                                        <th className="px-3 py-3">
                                            Item / Warna
                                        </th>
                                        <th className="px-3 py-3">Pkg</th>
                                        <th className="px-3 py-3 text-right">
                                            Qty DO
                                        </th>
                                        <th className="px-3 py-3 text-right">
                                            Qty Diterima
                                        </th>
                                        {(isApproved || canDecide) && (
                                            <th className="px-3 py-3 text-right">
                                                Selisih
                                            </th>
                                        )}
                                        {isApproved && (
                                            <>
                                                <th className="px-3 py-3 text-right">
                                                    Sudah Ditagih
                                                </th>
                                                <th className="px-3 py-3 text-right">
                                                    Sisa
                                                </th>
                                            </>
                                        )}
                                        <th className="px-3 py-3">UoM</th>
                                        {canWork && isDraft && (
                                            <>
                                                <th className="px-3 py-3">
                                                    Cek Fisik
                                                </th>
                                                <th className="px-3 py-3">
                                                    Master
                                                </th>
                                            </>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {visibleBundles.map((bundle, index) => {
                                        const first = bundle.group[0];
                                        const open =
                                            expanded[bundle.key] ?? false;

                                        return [
                                            <tr
                                                key={bundle.key}
                                                className="cursor-pointer hover:bg-surface-secondary/60"
                                                onClick={() =>
                                                    toggleExpand(bundle.key)
                                                }
                                            >
                                                <td className="px-3 py-3 text-muted">
                                                    {index + 1}
                                                </td>
                                                {(canWork || canDecide) && (
                                                    <td className="px-3 py-3">
                                                        <ReceiveStatus
                                                            verified={
                                                                bundle.verified
                                                            }
                                                        />
                                                    </td>
                                                )}
                                                <td className="px-3 py-3 font-mono text-xs font-semibold">
                                                    {bundle.key}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <p className="font-semibold">
                                                        {first.product_name}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted">
                                                        {first.sku}
                                                    </p>
                                                    <p className="mt-1 text-xs text-muted">
                                                        {bundle.group
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
                                                    {formatQty(bundle.totalDo)}
                                                </td>
                                                <td className="px-3 py-3 text-right font-bold">
                                                    {formatQty(
                                                        bundle.totalReceived,
                                                    )}
                                                </td>
                                                {(isApproved || canDecide) && (
                                                    <td
                                                        className={`px-3 py-3 text-right font-bold ${bundle.totalDo - bundle.totalReceived !== 0 ? 'text-danger' : 'text-muted'}`}
                                                    >
                                                        {bundle.totalDo -
                                                            bundle.totalReceived ===
                                                        0
                                                            ? '—'
                                                            : formatQty(
                                                                  bundle.totalDo -
                                                                      bundle.totalReceived,
                                                              )}
                                                    </td>
                                                )}
                                                {isApproved && (
                                                    <>
                                                        <td className="px-3 py-3 text-right text-muted">
                                                            {formatQty(
                                                                bundle.group.reduce(
                                                                    (sum, d) =>
                                                                        sum +
                                                                        Number(
                                                                            d.qty_invoiced ??
                                                                                0,
                                                                        ),
                                                                    0,
                                                                ),
                                                            )}
                                                        </td>
                                                        <td className="px-3 py-3 text-right font-bold text-accent">
                                                            {formatQty(
                                                                bundle.totalReceived -
                                                                    bundle.group.reduce(
                                                                        (
                                                                            sum,
                                                                            d,
                                                                        ) =>
                                                                            sum +
                                                                            Number(
                                                                                d.qty_invoiced ??
                                                                                    0,
                                                                            ),
                                                                        0,
                                                                    ),
                                                            )}
                                                        </td>
                                                    </>
                                                )}
                                                <td className="px-3 py-3">
                                                    {first.uom_name ?? '-'}
                                                </td>
                                                {canWork && isDraft && (
                                                    <>
                                                        <td className="px-3 py-3">
                                                            <input
                                                                type="checkbox"
                                                                checked={
                                                                    bundle.confirmed
                                                                }
                                                                disabled={
                                                                    !bundle.verified
                                                                }
                                                                onChange={(e) =>
                                                                    handleConfirm(
                                                                        first.id,
                                                                        e.target
                                                                            .checked,
                                                                    )
                                                                }
                                                                onClick={(e) =>
                                                                    e.stopPropagation()
                                                                }
                                                                className="size-4 accent-emerald-600"
                                                                aria-label={`Konfirmasi hitung fisik ${bundle.key}`}
                                                                title={
                                                                    bundle.verified
                                                                        ? 'Centang setelah qty dihitung fisik'
                                                                        : 'Scan barcode dulu'
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-3 py-3 text-xs">
                                                            {bundle.unmapped ===
                                                            0 ? (
                                                                <span className="font-semibold text-emerald-600">
                                                                    OK
                                                                </span>
                                                            ) : (
                                                                <span className="font-semibold text-danger">
                                                                    {
                                                                        bundle.unmapped
                                                                    }{' '}
                                                                    belum
                                                                    mapping
                                                                </span>
                                                            )}
                                                        </td>
                                                    </>
                                                )}
                                            </tr>,
                                            open && (
                                                <tr
                                                    key={`${bundle.key}-detail`}
                                                >
                                                    <td
                                                        colSpan={14}
                                                        className="bg-surface-secondary/40 px-6 py-3"
                                                    >
                                                        <table className="w-full text-left text-xs text-foreground">
                                                            <thead className="text-[11px] font-bold text-muted uppercase">
                                                                <tr>
                                                                    <th className="py-2 pr-3">
                                                                        Warna
                                                                    </th>
                                                                    <th className="py-2 pr-3 text-right">
                                                                        Qty DO
                                                                    </th>
                                                                    <th className="py-2 pr-3 text-right">
                                                                        Qty
                                                                        Diterima
                                                                    </th>
                                                                    {canWork &&
                                                                        isDraft && (
                                                                            <th className="py-2">
                                                                                Mapping
                                                                            </th>
                                                                        )}
                                                                </tr>
                                                            </thead>
                                                            <tbody className="divide-y divide-border/40">
                                                                {bundle.group.map(
                                                                    (
                                                                        detail,
                                                                    ) => (
                                                                        <tr
                                                                            key={
                                                                                detail.id
                                                                            }
                                                                        >
                                                                            <td className="py-2 pr-3 font-semibold">
                                                                                {detail.color_raw ||
                                                                                    '-'}
                                                                            </td>
                                                                            <td className="py-2 pr-3 text-right">
                                                                                {formatQty(
                                                                                    detail.qty_do,
                                                                                )}
                                                                            </td>
                                                                            <td className="py-2 pr-3 text-right">
                                                                                {canWork &&
                                                                                isDraft ? (
                                                                                    <input
                                                                                        type="number"
                                                                                        min={
                                                                                            0
                                                                                        }
                                                                                        max={Number(
                                                                                            detail.qty_do,
                                                                                        )}
                                                                                        step="any"
                                                                                        defaultValue={Number(
                                                                                            detail.qty_received,
                                                                                        )}
                                                                                        key={`qty-${detail.id}-${detail.qty_received}`}
                                                                                        onBlur={(
                                                                                            e,
                                                                                        ) =>
                                                                                            handleQty(
                                                                                                detail.id,
                                                                                                e
                                                                                                    .target
                                                                                                    .value,
                                                                                            )
                                                                                        }
                                                                                        onKeyDown={(
                                                                                            e,
                                                                                        ) => {
                                                                                            if (
                                                                                                e.key ===
                                                                                                'Enter'
                                                                                            ) {
                                                                                                (
                                                                                                    e.target as HTMLInputElement
                                                                                                ).blur();
                                                                                            }
                                                                                        }}
                                                                                        onClick={(
                                                                                            e,
                                                                                        ) =>
                                                                                            e.stopPropagation()
                                                                                        }
                                                                                        className="w-20 rounded-lg border border-border bg-surface px-2 py-1 text-right"
                                                                                        aria-label={`Qty diterima ${detail.color_raw}`}
                                                                                    />
                                                                                ) : (
                                                                                    <span className="font-bold">
                                                                                        {formatQty(
                                                                                            detail.qty_received,
                                                                                        )}
                                                                                    </span>
                                                                                )}
                                                                            </td>
                                                                            {canWork &&
                                                                                isDraft && (
                                                                                    <td className="py-2">
                                                                                        {detail.product_variant_id !==
                                                                                        null ? (
                                                                                            <span className="font-semibold text-emerald-600">
                                                                                                Otomatis
                                                                                            </span>
                                                                                        ) : (
                                                                                            <span className="text-muted">
                                                                                                Belum
                                                                                                termapping
                                                                                            </span>
                                                                                        )}
                                                                                    </td>
                                                                                )}
                                                                        </tr>
                                                                    ),
                                                                )}
                                                            </tbody>
                                                        </table>
                                                    </td>
                                                </tr>
                                            ),
                                        ];
                                    })}
                                    {visibleBundles.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={14}
                                                className="px-3 py-8 text-center text-muted"
                                            >
                                                Tidak ada barang yang cocok.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>

            <PurchaseConfirmDialog
                open={confirmSubmit}
                title="Kirim GRN ke HO?"
                description="Pastikan semua bundle terverifikasi dan qty sudah sesuai fisik. Stok belum bertambah sebelum HO menyetujui."
                confirmLabel="Kirim"
                onConfirm={handleSubmit}
                onClose={() => setConfirmSubmit(false)}
            />

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
