import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import PurchaseConfirmDialog from '@/components/purchasing/purchase-confirm-dialog';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatDate } from '@/lib/format';
import { GoodsReceiptStatus } from '@/lib/purchasing/status';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    qty_received: number;
};

type GoodsReceipt = {
    id: number;
    number: string;
    purchase_order_id: number;
    warehouse_id: number;
    status: string;
    receipt_date: string;
    note?: string;
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

export default function GoodsReceiptsShow({ goodsReceipt, warehouse }: Props) {
    const [confirmPost, setConfirmPost] = useState(false);
    const { post: postPost, processing: posting } = useForm({});

    const handlePost = () => {
        postPost(`/purchasing/grns/${goodsReceipt.id}/post`);
    };

    const rows: DetailRow[] = [
        {
            label: 'Tanggal terima',
            value: formatDate(goodsReceipt.receipt_date),
        },
        {
            label: 'No. PO',
            value: goodsReceipt.purchase_order ? (
                <Link
                    href={`/purchasing/orders/${goodsReceipt.purchase_order_id}`}
                    className="text-accent hover:underline"
                >
                    #{goodsReceipt.purchase_order.number}
                </Link>
            ) : (
                `#${goodsReceipt.purchase_order_id}`
            ),
        },
        {
            label: 'Gudang',
            value: warehouse
                ? `${warehouse.code} — ${warehouse.name}`
                : `#${goodsReceipt.warehouse_id}`,
        },
    ];

    return (
        <CompanyLayout>
            <Head title={`GRN #${goodsReceipt.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Penerimaan"
                    title={`Goods Receipt #${goodsReceipt.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/grns">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            {goodsReceipt.status ===
                                GoodsReceiptStatus.Draft && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onPress={() => setConfirmPost(true)}
                                >
                                    Posting Stok ke Gudang
                                </Button>
                            )}
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={goodsReceipt.status}
                    statusLabel="Status Penerimaan"
                    rows={rows}
                    note={goodsReceipt.note}
                >
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Diterima
                        </h3>
                        <div className="overflow-hidden rounded-lg border border-border">
                            <table className="w-full text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-4 py-3">Produk</th>
                                        <th className="px-4 py-3">SKU</th>
                                        <th className="px-4 py-3 text-right">
                                            Jumlah Diterima
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {goodsReceipt.items.map((item) => (
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
                                            <td className="px-4 py-3 text-right font-bold text-foreground">
                                                {item.qty_received}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>

            <PurchaseConfirmDialog
                open={confirmPost}
                title="Posting Penerimaan Barang?"
                description="Stok akan dimasukkan ke gudang dan status pesanan pembelian diperbarui. Tindakan ini tidak dapat dibatalkan."
                confirmLabel="Posting"
                processing={posting}
                onConfirm={handlePost}
                onClose={() => setConfirmPost(false)}
            />
        </CompanyLayout>
    );
}
