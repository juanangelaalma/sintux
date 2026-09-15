import { Button } from '@heroui/react';
import { Head, Link } from '@inertiajs/react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';
import SalesStatusBadge from './status-badge';

type Item = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string | null;
    qty: number;
    unit_price: number;
    discount_type?: string | null;
    discount_value: number;
    discount_amount: number;
    line_gross: number;
    line_net: number;
    tax_rate: number;
    tax_amount: number;
    line_total: number;
};

type SalesInvoice = {
    id: number;
    number: string;
    status: string;
    transaction_type: string;
    customer_name: string;
    customer_email?: string | null;
    warehouse_code: string;
    warehouse_name: string;
    salesperson_name: string;
    payment_term?: string | null;
    invoice_date: string;
    due_date?: string | null;
    is_tax_inclusive: boolean;
    subtotal: number;
    line_discount_total: number;
    invoice_discount_type?: string | null;
    invoice_discount_value: number;
    invoice_discount_amount: number;
    tax_amount: number;
    total: number;
    items: Item[];
};

type Props = {
    salesInvoice: SalesInvoice;
    approval?: ApprovalStatusProps | null;
};

const discountLabel = (item: Item): string => {
    if (!item.discount_type) {
        return '-';
    }

    return item.discount_type === 'percent'
        ? `${Number(item.discount_value)}%`
        : formatCurrency(item.discount_value);
};

export default function SalesInvoicesShow({ salesInvoice, approval }: Props) {
    return (
        <CompanyLayout>
            <Head title={`Faktur #${salesInvoice.number}`} />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Penjualan / Detail Faktur
                        </p>
                        <div className="mt-1 flex items-center gap-3">
                            <h1 className="text-2xl font-bold text-foreground">
                                Faktur #{salesInvoice.number}
                            </h1>
                            <SalesStatusBadge status={salesInvoice.status} />
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Link href="/sales/invoices">
                            <Button type="button" variant="secondary">
                                Kembali
                            </Button>
                        </Link>
                        <ApprovalHeaderControls
                            approval={approval}
                            documentTitle={salesInvoice.number}
                        />
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 rounded-xl border border-border bg-surface p-6 text-sm md:grid-cols-3">
                    <div>
                        <p className="text-xs font-semibold text-muted">
                            Pelanggan
                        </p>
                        <p className="mt-1 font-semibold text-foreground">
                            {salesInvoice.customer_name}
                        </p>
                        {salesInvoice.customer_email && (
                            <p className="text-xs text-muted">
                                {salesInvoice.customer_email}
                            </p>
                        )}
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-muted">
                            Gudang
                        </p>
                        <p className="mt-1 font-semibold text-foreground">
                            {salesInvoice.warehouse_code} —{' '}
                            {salesInvoice.warehouse_name}
                        </p>
                        <p className="text-xs text-muted">
                            {salesInvoice.transaction_type === 'consignment'
                                ? 'Konsinyasi'
                                : 'Reguler'}
                            {' · '}
                            {salesInvoice.is_tax_inclusive
                                ? 'Harga termasuk pajak'
                                : 'Harga belum termasuk pajak'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-muted">
                            Sales / Marketing
                        </p>
                        <p className="mt-1 font-semibold text-foreground">
                            {salesInvoice.salesperson_name}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-muted">
                            Tgl. Transaksi
                        </p>
                        <p className="mt-1 font-medium text-foreground">
                            {formatDate(salesInvoice.invoice_date)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-muted">
                            Tgl. Jatuh Tempo
                        </p>
                        <p className="mt-1 font-medium text-foreground">
                            {formatDate(salesInvoice.due_date)}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold text-muted">
                            Syarat Pembayaran
                        </p>
                        <p className="mt-1 font-medium text-foreground">
                            {salesInvoice.payment_term || '-'}
                        </p>
                    </div>
                </div>

                <div>
                    <h3 className="mb-3 text-sm font-bold text-foreground">
                        Item Penjualan
                    </h3>
                    <div className="overflow-hidden rounded-lg border border-border">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[860px] text-left text-sm text-foreground">
                                <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                    <tr>
                                        <th className="px-4 py-3">Produk</th>
                                        <th className="px-4 py-3 text-right">
                                            Qty
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Harga
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Diskon
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Pajak
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Jumlah
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border/60">
                                    {salesInvoice.items.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-surface-secondary/60"
                                        >
                                            <td className="px-4 py-3">
                                                <span className="font-semibold text-foreground">
                                                    {item.product_name}
                                                </span>
                                                <span className="ml-2 font-mono text-xs text-muted">
                                                    {item.sku}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium">
                                                {item.qty}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatCurrency(
                                                    item.unit_price,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <span className="block text-xs text-muted">
                                                    {discountLabel(item)}
                                                </span>
                                                <span>
                                                    −
                                                    {formatCurrency(
                                                        item.discount_amount,
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <span className="block text-xs text-muted">
                                                    {Number(item.tax_rate)}%
                                                </span>
                                                <span>
                                                    {formatCurrency(
                                                        item.tax_amount,
                                                    )}
                                                </span>
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
                </div>

                <div className="flex justify-end border-t border-border/60 pt-4">
                    <div className="w-80 space-y-1.5 text-sm">
                        <div className="flex justify-between text-muted">
                            <span>Subtotal</span>
                            <span>{formatCurrency(salesInvoice.subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-muted">
                            <span>Diskon Perbaris</span>
                            <span>
                                {formatCurrency(
                                    salesInvoice.line_discount_total,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between text-muted">
                            <span>Diskon Invoice</span>
                            <span>
                                −
                                {formatCurrency(
                                    salesInvoice.invoice_discount_amount,
                                )}
                            </span>
                        </div>
                        <div className="flex justify-between text-muted">
                            <span>Pajak</span>
                            <span>
                                {formatCurrency(salesInvoice.tax_amount)}
                            </span>
                        </div>
                        <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                            <span>TOTAL</span>
                            <span>{formatCurrency(salesInvoice.total)}</span>
                        </div>
                        <div className="flex justify-between text-base font-black text-foreground">
                            <span>Sisa Tagihan</span>
                            <span>{formatCurrency(salesInvoice.total)}</span>
                        </div>
                    </div>
                </div>
            </div>
        </CompanyLayout>
    );
}
