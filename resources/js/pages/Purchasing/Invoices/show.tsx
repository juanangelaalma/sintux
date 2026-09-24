import { Button, Dropdown, Label } from '@heroui/react';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate, formatQty } from '@/lib/format';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
    color_raw?: string | null;
    qty: number;
    qty_returned?: number;
    unit_price: number;
    line_total: number;
};

type InvoiceReturn = {
    id: number;
    number: string;
    status: string;
    return_date: string;
    total: number;
    memo?: string | null;
};

type PurchaseInvoice = {
    id: number;
    number: string;
    status: string;
    invoice_date: string;
    due_date?: string;
    note?: string;
    supplier_invoice_no?: string | null;
    tax_invoice_no?: string | null;
    subtotal: number;
    tax_amount: number;
    total: number;
    items: Item[];
    goods_receipt?: {
        id: number;
        number: string;
    } | null;
};

type Props = {
    purchaseInvoice: PurchaseInvoice;
    approval?: ApprovalStatusProps | null;
    returnContext?: {
        canReturn: boolean;
        returns: InvoiceReturn[];
    };
    paymentContext?: {
        canPay: boolean;
        outstanding: number;
    };
};

export default function PurchaseInvoicesShow({
    purchaseInvoice,
    approval,
    returnContext,
    paymentContext,
}: Props) {
    const canReturn = returnContext?.canReturn ?? false;
    const canPay = paymentContext?.canPay ?? false;
    const returns = returnContext?.returns ?? [];
    const rows: DetailRow[] = [
        {
            label: 'Tanggal faktur',
            value: formatDate(purchaseInvoice.invoice_date),
        },
        {
            label: 'Tanggal jatuh tempo',
            value: formatDate(purchaseInvoice.due_date),
        },
        ...(purchaseInvoice.goods_receipt
            ? [
                  {
                      label: 'Penerimaan (GRN)',
                      value: (
                          <Link
                              href={`/purchasing/grns/${purchaseInvoice.goods_receipt.id}`}
                              className="text-accent hover:underline"
                          >
                              #{purchaseInvoice.goods_receipt.number}
                          </Link>
                      ),
                  } as DetailRow,
              ]
            : []),
        {
            label: 'No. faktur supplier',
            value: purchaseInvoice.supplier_invoice_no ?? '—',
        },
        {
            label: 'No. faktur pajak',
            value: purchaseInvoice.tax_invoice_no ?? '—',
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
                            {canReturn ? (
                                <Dropdown>
                                    <Button
                                        type="button"
                                        variant="primary"
                                        className="gap-2 font-semibold"
                                    >
                                        Tindakan
                                        <ChevronDown className="size-4" />
                                    </Button>
                                    <Dropdown.Popover>
                                        <Dropdown.Menu
                                            onAction={(key) =>
                                                router.get(
                                                    String(key),
                                                    {},
                                                    { preserveState: true },
                                                )
                                            }
                                        >
                                            {canPay ? (
                                                <Dropdown.Item
                                                    key={`/purchase-payments/new?createdFrom=${purchaseInvoice.id}`}
                                                    id={`/purchase-payments/new?createdFrom=${purchaseInvoice.id}`}
                                                    textValue="Kirim Pembayaran"
                                                >
                                                    <Label>
                                                        Kirim Pembayaran
                                                    </Label>
                                                </Dropdown.Item>
                                            ) : null}
                                            {canPay || canReturn ? (
                                                <Dropdown.Item
                                                    key={`/purchasing/returns/new?createdFrom=${purchaseInvoice.id}`}
                                                    id={`/purchasing/returns/new?createdFrom=${purchaseInvoice.id}`}
                                                    textValue="Retur Pembelian"
                                                >
                                                    <Label>
                                                        Retur Pembelian
                                                    </Label>
                                                </Dropdown.Item>
                                            ) : null}
                                        </Dropdown.Menu>
                                    </Dropdown.Popover>
                                </Dropdown>
                            ) : (
                                <Button
                                    type="button"
                                    variant="primary"
                                    className="gap-2 font-semibold"
                                    isDisabled
                                >
                                    Tindakan
                                    <ChevronDown className="size-4" />
                                </Button>
                            )}
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
                    notePosition="bottom"
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
                                        <th className="px-4 py-3">Warna</th>
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
                                            <td className="px-4 py-3 font-medium">
                                                {item.color_raw ?? '-'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {formatQty(item.qty)}
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
                            <div className="flex justify-between text-muted">
                                <span>Pajak (PPN)</span>
                                <span>
                                    {formatCurrency(purchaseInvoice.tax_amount)}
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

                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Riwayat Retur
                        </h3>
                        {returns.length === 0 ? (
                            <div
                                role="status"
                                className="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted"
                            >
                                Belum ada retur untuk faktur ini.
                            </div>
                        ) : (
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="w-full text-left text-sm text-foreground">
                                    <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Nomor Retur
                                            </th>
                                            <th className="px-4 py-3">
                                                Tanggal
                                            </th>
                                            <th className="px-4 py-3">
                                                Status
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Nilai Retur
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {returns.map((retur) => (
                                            <tr
                                                key={retur.id}
                                                className="hover:bg-surface-secondary/60"
                                            >
                                                <td className="px-4 py-3">
                                                    <Link
                                                        href={`/purchasing/returns/${retur.id}`}
                                                        className="font-semibold text-accent hover:underline"
                                                    >
                                                        {retur.number}
                                                    </Link>
                                                    {retur.memo && (
                                                        <p className="mt-0.5 text-xs text-muted">
                                                            {retur.memo}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {formatDate(
                                                        retur.return_date,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <PurchasingStatusBadge
                                                        status={retur.status}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 text-right font-bold">
                                                    {formatCurrency(
                                                        retur.total,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </div>
                </PurchaseDocumentDetail>
            </div>
        </CompanyLayout>
    );
}
