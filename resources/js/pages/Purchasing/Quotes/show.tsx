import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
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
    qty: number;
    unit_price: number;
    line_total: number;
};

type PurchaseQuote = {
    id: number;
    number: string;
    status: string;
    quote_date: string;
    valid_until?: string;
    note?: string;
    subtotal: number;
    total: number;
    items: Item[];
};

type Props = {
    purchaseQuote: PurchaseQuote;
};

export default function PurchaseQuotesShow({ purchaseQuote }: Props) {
    const [confirmAccept, setConfirmAccept] = useState(false);
    const { post: postAccept, processing: accepting } = useForm({});

    const handleAccept = () => {
        postAccept(`/purchasing/quotes/${purchaseQuote.id}/accept`);
    };

    const rows: DetailRow[] = [
        {
            label: 'Tanggal penawaran',
            value: formatDate(purchaseQuote.quote_date),
        },
        {
            label: 'Berlaku hingga',
            value: formatDate(purchaseQuote.valid_until),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={`Penawaran #${purchaseQuote.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Penawaran"
                    title={`Purchase Quote #${purchaseQuote.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/quotes">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            {purchaseQuote.status === 'draft' && (
                                <Button
                                    type="button"
                                    variant="primary"
                                    onPress={() => setConfirmAccept(true)}
                                >
                                    Terima Penawaran
                                </Button>
                            )}
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={purchaseQuote.status}
                    statusLabel="Status Penawaran"
                    rows={rows}
                    note={purchaseQuote.note}
                >
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Penawaran
                        </h3>
                        <div className="overflow-hidden rounded-lg border border-border">
                            <table className="w-full text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-4 py-3">Produk</th>
                                        <th className="px-4 py-3">SKU</th>
                                        <th className="px-4 py-3 text-right">
                                            Qty
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
                                    {purchaseQuote.items.map((item) => (
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
                                                {item.qty}
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
                                    {formatCurrency(purchaseQuote.subtotal)}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                                <span>Total Penawaran</span>
                                <span>
                                    {formatCurrency(purchaseQuote.total)}
                                </span>
                            </div>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>

            <PurchaseConfirmDialog
                open={confirmAccept}
                title="Terima Penawaran Harga?"
                description="Penawaran ini akan diterima dan dapat diproses menjadi Pesanan Pembelian (PO)."
                confirmLabel="Terima Penawaran"
                processing={accepting}
                onConfirm={handleAccept}
                onClose={() => setConfirmAccept(false)}
            />
        </CompanyLayout>
    );
}
