import { Button } from '@heroui/react';
import { Head, Link } from '@inertiajs/react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
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

type PurchaseInvoice = {
    id: number;
    number: string;
    status: string;
    invoice_date: string;
    due_date?: string;
    note?: string;
    subtotal: number;
    tax_amount: number;
    total: number;
    items: Item[];
};

type Props = {
    purchaseInvoice: PurchaseInvoice;
    approval?: ApprovalStatusProps | null;
};

export default function PurchaseInvoicesShow({
    purchaseInvoice,
    approval,
}: Props) {
    const rows: DetailRow[] = [
        {
            label: 'Tanggal faktur',
            value: formatDate(purchaseInvoice.invoice_date),
        },
        {
            label: 'Tanggal jatuh tempo',
            value: formatDate(purchaseInvoice.due_date),
        },
        {
            label: 'Pajak (PPN)',
            value: formatCurrency(purchaseInvoice.tax_amount),
        },
    ];

    return (
        <CompanyLayout>
            <Head title={`Faktur #${purchaseInvoice.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Faktur"
                    title={`Purchase Invoice #${purchaseInvoice.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/invoices">
                                <Button type="button" variant="secondary">
                                    Kembali
                                </Button>
                            </Link>
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={purchaseInvoice.number}
                            />
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={purchaseInvoice.status}
                    statusLabel="Status Faktur"
                    rows={rows}
                    note={purchaseInvoice.note}
                >
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Tagihan
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
                                    {purchaseInvoice.items.map((item) => (
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
                                    {formatCurrency(purchaseInvoice.subtotal)}
                                </span>
                            </div>
                            <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                                <span>Total Tagihan</span>
                                <span>
                                    {formatCurrency(purchaseInvoice.total)}
                                </span>
                            </div>
                        </div>
                    </div>
                </PurchaseDocumentDetail>
            </div>
        </CompanyLayout>
    );
}
