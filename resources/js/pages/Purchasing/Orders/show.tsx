import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import { BranchAllocationShowCard } from '@/components/purchasing/branch-allocation/BranchAllocationShowCard';
import PurchaseConfirmDialog from '@/components/purchasing/purchase-confirm-dialog';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';
import { groupAndSortByBranch } from '@/lib/purchasing/grouping';
import { PurchaseOrderStatus } from '@/lib/purchasing/status';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty_ordered: number;
    qty_received: number;
    unit_price: number;
    line_total: number;
    destination_branch_id?: number;
    destination_warehouse_id?: number;
    destination_expected_date?: string | null;
    destination_branch?: { id: number; name: string; code: string };
    destination_warehouse?: { id: number; code: string; name: string };
};

type PurchaseOrder = {
    id: number;
    number: string;
    status: string;
    branch_mode?: string;
    order_date: string;
    expected_date?: string;
    supplier_reference?: string;
    note?: string;
    subtotal: number;
    total: number;
    items: Item[];
    tags?: { id: number; name: string }[];
};

type Props = {
    purchaseOrder: PurchaseOrder;
    approval?: ApprovalStatusProps | null;
};

export default function PurchaseOrdersShow({ purchaseOrder, approval }: Props) {
    const [confirmAction, setConfirmAction] = useState<
        'send' | 'cancel' | null
    >(null);
    const { post: postSend, processing: sending } = useForm({});
    const { post: postCancel, processing: cancelling } = useForm({});

    const processing =
        (confirmAction === 'send' && sending) ||
        (confirmAction === 'cancel' && cancelling);

    const handleConfirm = () => {
        if (confirmAction === 'send') {
            postSend(`/purchasing/orders/${purchaseOrder.id}/send`);
        }

        if (confirmAction === 'cancel') {
            postCancel(`/purchasing/orders/${purchaseOrder.id}/cancel`);
        }
    };

    const branchGroups = useMemo(
        () => groupAndSortByBranch(purchaseOrder.items),
        [purchaseOrder.items],
    );

    const [expandedBranches, setExpandedBranches] = useState<Set<string>>(
        () => new Set(branchGroups.map((g) => g.key)),
    );

    const toggleBranch = (key: string) => {
        setExpandedBranches((prev) => {
            const n = new Set(prev);

            if (n.has(key)) {
                n.delete(key);
            } else {
                n.add(key);
            }

            return n;
        });
    };

    const rows: DetailRow[] = [
        {
            label: 'Tanggal pesanan',
            value: formatDate(purchaseOrder.order_date),
        },
        {
            label: 'Perkiraan tiba',
            value: formatDate(purchaseOrder.expected_date),
        },
        {
            label: 'Mode',
            value:
                purchaseOrder.branch_mode === 'multi'
                    ? `Multi-Cabang (${branchGroups.length} cabang)`
                    : 'Single',
        },
        ...(purchaseOrder.supplier_reference
            ? [
                  {
                      label: 'Referensi supplier',
                      value: purchaseOrder.supplier_reference,
                  },
              ]
            : []),
        ...(purchaseOrder.tags?.length
            ? [
                  {
                      label: 'Tag',
                      value: purchaseOrder.tags
                          .map((tag) => tag.name)
                          .join(', '),
                  },
              ]
            : []),
    ];

    return (
        <CompanyLayout>
            <Head title={`PO #${purchaseOrder.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Pesanan"
                    title={`Purchase Order #${purchaseOrder.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/orders">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={purchaseOrder.number}
                            />
                            {purchaseOrder.status ===
                                PurchaseOrderStatus.Approved && (
                                <>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        onPress={() => setConfirmAction('send')}
                                    >
                                        Kirim ke Supplier
                                    </Button>
                                    <Link href="/purchasing/grns/create">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Terima Barang
                                        </Button>
                                    </Link>
                                    <Link href="/purchasing/invoices/create">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Buat Faktur
                                        </Button>
                                    </Link>
                                    <Button
                                        type="button"
                                        variant="danger"
                                        onPress={() =>
                                            setConfirmAction('cancel')
                                        }
                                    >
                                        Batalkan PO
                                    </Button>
                                </>
                            )}
                            {(purchaseOrder.status ===
                                PurchaseOrderStatus.Sent ||
                                purchaseOrder.status ===
                                    PurchaseOrderStatus.PartiallyReceived) && (
                                <>
                                    <Link href="/purchasing/grns/create">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Terima Barang{' '}
                                            {purchaseOrder.status ===
                                            PurchaseOrderStatus.PartiallyReceived
                                                ? '(Sisa)'
                                                : ''}
                                        </Button>
                                    </Link>
                                    <Link href="/purchasing/invoices/create">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                        >
                                            Buat Faktur
                                        </Button>
                                    </Link>
                                    {purchaseOrder.status ===
                                        PurchaseOrderStatus.Sent && (
                                        <Button
                                            type="button"
                                            variant="danger"
                                            onPress={() =>
                                                setConfirmAction('cancel')
                                            }
                                        >
                                            Batalkan PO
                                        </Button>
                                    )}
                                </>
                            )}
                            {purchaseOrder.status ===
                                PurchaseOrderStatus.Received && (
                                <Link href="/purchasing/invoices/create">
                                    <Button type="button" variant="secondary">
                                        Buat Faktur
                                    </Button>
                                </Link>
                            )}
                            {purchaseOrder.status ===
                                PurchaseOrderStatus.Pending && (
                                <Button
                                    type="button"
                                    variant="danger"
                                    onPress={() => setConfirmAction('cancel')}
                                >
                                    Batalkan PO
                                </Button>
                            )}
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={purchaseOrder.status}
                    statusLabel="Status Pesanan"
                    rows={rows}
                    note={purchaseOrder.note}
                >
                    <div className="space-y-4">
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h3 className="text-sm font-bold text-foreground">
                                Item Pesanan{' '}
                                {purchaseOrder.branch_mode === 'multi'
                                    ? `— ${branchGroups.length} Cabang Tujuan`
                                    : ''}
                            </h3>
                            <span className="text-xs text-muted">
                                urut tanggal kirim tercepat di atas
                            </span>
                        </div>

                        {branchGroups.length === 0 ? (
                            <p className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted">
                                Belum ada alokasi cabang.
                            </p>
                        ) : (
                            <div className="space-y-3">
                                {branchGroups.map((group) => (
                                    <BranchAllocationShowCard
                                        key={group.key}
                                        branchCode={group.branchCode}
                                        branch={group.branch}
                                        warehouse={group.warehouse}
                                        date={group.date}
                                        items={group.items}
                                        subtotal={group.subtotal}
                                        isExpanded={expandedBranches.has(
                                            group.key,
                                        )}
                                        onToggle={() => toggleBranch(group.key)}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end border-t border-border/60 pt-4">
                        <div className="w-72 space-y-2 text-sm">
                            <div className="flex justify-between text-muted">
                                <span>Subtotal</span>
                                <span>
                                    {formatCurrency(purchaseOrder.subtotal)}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                                <span>Total Pesanan</span>
                                <span>
                                    {formatCurrency(purchaseOrder.total)}
                                </span>
                            </div>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>

            <PurchaseConfirmDialog
                open={confirmAction !== null}
                title={
                    confirmAction === 'cancel'
                        ? 'Batalkan Purchase Order?'
                        : 'Konfirmasi Aksi'
                }
                description={
                    confirmAction === 'send'
                        ? 'Tandai PO ini sebagai dikirim ke pemasok?'
                        : 'Batalkan Purchase Order ini?'
                }
                confirmLabel={
                    confirmAction === 'cancel' ? 'Batalkan' : 'Konfirmasi'
                }
                variant={confirmAction === 'cancel' ? 'danger' : 'primary'}
                processing={processing}
                onConfirm={handleConfirm}
                onClose={() => setConfirmAction(null)}
            />
        </CompanyLayout>
    );
}
