import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import PurchaseConfirmDialog from '@/components/purchasing/purchase-confirm-dialog';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty_ordered: number;
    qty_received: number;
    unit_price: number;
    line_total: number;
};

type PurchaseOrder = {
    id: number;
    number: string;
    status: string;
    order_date: string;
    expected_date?: string;
    note?: string;
    subtotal: number;
    total: number;
    items: Item[];
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

    const rows: DetailRow[] = [
        {
            label: 'Tanggal pesanan',
            value: formatDate(purchaseOrder.order_date),
        },
        {
            label: 'Perkiraan tiba',
            value: formatDate(purchaseOrder.expected_date),
        },
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
                            {purchaseOrder.status === 'approved' && (
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
                            {(purchaseOrder.status === 'sent' ||
                                purchaseOrder.status === 'received') && (
                                <>
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
                            {purchaseOrder.status === 'pending' && (
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
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Pesanan
                        </h3>
                        <div className="overflow-hidden rounded-lg border border-border">
                            <table className="w-full text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-4 py-3">Produk</th>
                                        <th className="px-4 py-3">SKU</th>
                                        <th className="px-4 py-3 text-right">
                                            Dipesan
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Diterima
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Harga Satuan
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Jumlah
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {purchaseOrder.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-surface-secondary/60"
                                        >
                                            <td className="px-4 py-3 font-semibold text-foreground">
                                                {item.product_name}
                                            </td>
                                            <td className="px-4 py-3 font-mono text-xs text-muted">
                                                {item.sku}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {item.qty_ordered}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {item.qty_received}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatCurrency(
                                                    item.unit_price,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right font-bold text-foreground">
                                                {formatCurrency(
                                                    item.line_total,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
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
