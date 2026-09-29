import { Button, Chip } from '@heroui/react';
import { Link } from '@inertiajs/react';
import { Paperclip, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';
import { formatCurrency, formatDate, formatQty } from '@/lib/format';

export type InvoiceItem = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string | null;
    color_raw?: string | null;
    qty: number;
    qty_returned?: number;
    unit_price: number;
    line_total: number;
};

export type InvoiceSupplier = {
    name: string;
    email: string | null;
    address: string;
};

export type InvoicePayment = {
    id: number;
    number: string;
    payment_date: string;
    payment_method: string | null;
    status: string;
    amount: number;
    memo: string | null;
};

export type InvoiceReturn = {
    id: number;
    number: string;
    status: string;
    return_date: string;
    total: number;
    memo?: string | null;
};

export type InvoiceJournalLine = {
    account_code: string;
    account_name: string;
    debit: number;
    credit: number;
    memo?: string | null;
};

export type InvoiceJournal = {
    id: number;
    memo: string | null;
    journal_date: string;
    lines: InvoiceJournalLine[];
};

export type InvoiceDocument = {
    id: number;
    number: string;
    status: string;
    invoice_date: string;
    due_date?: string | null;
    note?: string | null;
    supplier_invoice_no?: string | null;
    tax_invoice_no?: string | null;
    subtotal: number;
    tax_amount: number;
    total: number;
    paid_amount: number;
    returned_amount: number;
    outstanding: number;
    updated_at?: string | null;
    items: InvoiceItem[];
    purchase_order?: { id: number; number: string } | null;
    goods_receipt?: { id: number; number: string } | null;
};

type MetaValueProps = {
    label: string;
    children: ReactNode;
};

function MetaValue({ label, children }: MetaValueProps) {
    return (
        <div className="min-w-0">
            <p className="text-xs text-gray-500">{label}</p>
            <div className="mt-1 text-sm font-semibold break-words text-gray-900 dark:text-gray-100">
                {children}
            </div>
        </div>
    );
}

type InvoiceMetaPanelProps = {
    invoice: InvoiceDocument;
    supplier: InvoiceSupplier;
    journal: InvoiceJournal | null;
    onOpenJournal: () => void;
};

export function InvoiceMetaPanel({
    invoice,
    supplier,
    journal,
    onOpenJournal,
}: InvoiceMetaPanelProps) {
    return (
        <section className="border-b border-gray-200 bg-white px-5 py-5 sm:px-8 dark:border-gray-800 dark:bg-gray-950">
            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <div className="grid gap-5 sm:grid-cols-2">
                    <MetaValue label="Supplier">
                        {supplier.name || '-'}
                    </MetaValue>
                    <MetaValue label="Email">{supplier.email || '-'}</MetaValue>
                </div>

                <div className="text-left lg:text-right">
                    <p className="text-xs text-gray-500">Sisa tagihan</p>
                    <p className="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-gray-50">
                        {formatCurrency(invoice.outstanding)}
                    </p>
                    {journal ? (
                        <button
                            type="button"
                            onClick={onOpenJournal}
                            className="mt-1 text-sm font-medium text-brand-600 hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:text-brand-400"
                        >
                            Lihat journal entry
                        </button>
                    ) : null}
                </div>
            </div>

            <div className="my-5 border-t border-dashed border-gray-300 dark:border-gray-700" />

            <div className="grid gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                <MetaValue label="Alamat supplier">
                    {supplier.address || '-'}
                </MetaValue>
                <div className="grid grid-cols-2 gap-x-4 gap-y-3 sm:col-span-1">
                    <MetaValue label="Tgl. transaksi">
                        {formatDate(invoice.invoice_date)}
                    </MetaValue>
                    <MetaValue label="Tgl. jatuh tempo">
                        {formatDate(invoice.due_date)}
                    </MetaValue>
                    <MetaValue label="Syarat pembayaran">-</MetaValue>
                    <MetaValue label="No. transaksi">
                        {invoice.number}
                    </MetaValue>
                </div>
                <div className="grid grid-cols-2 gap-x-4 gap-y-3 sm:col-span-1">
                    <MetaValue label="No. referensi">
                        {invoice.purchase_order?.number || '-'}
                    </MetaValue>
                    <MetaValue label="Faktur supplier">
                        {invoice.supplier_invoice_no || '-'}
                    </MetaValue>
                    <MetaValue label="Faktur pajak">
                        {invoice.tax_invoice_no || '-'}
                    </MetaValue>
                    <MetaValue label="Tag">-</MetaValue>
                </div>
            </div>
        </section>
    );
}

type InvoiceItemsPanelProps = {
    items: InvoiceItem[];
};

export function InvoiceItemsPanel({ items }: InvoiceItemsPanelProps) {
    return (
        <section className="px-5 py-5 sm:px-8 dark:bg-gray-950">
            <div className="mb-3 flex items-center justify-between gap-3">
                <h2 className="text-sm font-bold text-gray-900 dark:text-gray-100">
                    Item Faktur
                </h2>
                <span className="text-xs text-gray-500">
                    {items.length} produk
                </span>
            </div>

            {items.length === 0 ? (
                <div
                    role="status"
                    className="border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-700"
                >
                    Belum ada item pada faktur ini.
                </div>
            ) : (
                <div className="overflow-x-auto border border-gray-200 dark:border-gray-800">
                    <table className="w-full min-w-[980px] text-left text-sm text-gray-800 dark:text-gray-200">
                        <thead className="border-b border-gray-200 bg-gray-50 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                            <tr>
                                <th className="px-4 py-3">Produk</th>
                                <th className="px-4 py-3">Deskripsi</th>
                                <th className="px-4 py-3 text-right">
                                    Kuantitas
                                </th>
                                <th className="px-4 py-3">Unit</th>
                                <th className="px-4 py-3 text-right">
                                    Harga satuan
                                </th>
                                <th className="px-4 py-3 text-right">Diskon</th>
                                <th className="px-4 py-3 text-right">Jumlah</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                            {items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/60"
                                >
                                    <td className="px-4 py-3">
                                        <p className="font-semibold text-brand-600 dark:text-brand-400">
                                            {item.product_name}
                                        </p>
                                        <p className="mt-0.5 font-mono text-xs text-gray-500">
                                            {item.sku}
                                        </p>
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {item.color_raw || '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium">
                                        {formatQty(item.qty)}
                                    </td>
                                    <td className="px-4 py-3 text-gray-600 dark:text-gray-400">
                                        {item.uom_name || '-'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {formatCurrency(item.unit_price)}
                                    </td>
                                    <td className="px-4 py-3 text-right text-gray-600 dark:text-gray-400">
                                        0%
                                    </td>
                                    <td className="px-4 py-3 text-right font-semibold text-gray-950 dark:text-gray-50">
                                        {formatCurrency(item.line_total)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {items.length > 0 ? (
                <p className="mt-3 text-xs text-gray-500">
                    Menampilkan {items.length} dari {items.length} produk
                </p>
            ) : null}
        </section>
    );
}

type InvoiceSummaryPanelProps = {
    invoice: InvoiceDocument;
};

function formatDateTime(value?: string | null): string {
    if (!value) {
        return '-';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString('id-ID', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function InvoiceSummaryPanel({ invoice }: InvoiceSummaryPanelProps) {
    return (
        <section className="border-t border-gray-200 px-5 py-5 sm:px-8 dark:border-gray-800">
            <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div className="space-y-4">
                    <div className="flex flex-wrap gap-x-12 gap-y-3 text-sm">
                        <div>
                            <p className="text-xs text-gray-500">Pesanan</p>
                            <p className="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {invoice.purchase_order ? (
                                    <Link
                                        href={`/purchasing/orders/${invoice.purchase_order.id}`}
                                        className="text-brand-600 hover:underline dark:text-brand-400"
                                    >
                                        #{invoice.purchase_order.number}
                                    </Link>
                                ) : (
                                    '-'
                                )}
                            </p>
                        </div>
                        <div>
                            <p className="text-xs text-gray-500">
                                Penerimaan (GRN)
                            </p>
                            <p className="mt-1 font-semibold text-gray-800 dark:text-gray-200">
                                {invoice.goods_receipt ? (
                                    <Link
                                        href={`/purchasing/grns/${invoice.goods_receipt.id}`}
                                        className="text-brand-600 hover:underline dark:text-brand-400"
                                    >
                                        #{invoice.goods_receipt.number}
                                    </Link>
                                ) : (
                                    '-'
                                )}
                            </p>
                        </div>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500">Memo</p>
                        <p className="mt-1 text-sm whitespace-pre-wrap text-gray-800 dark:text-gray-200">
                            {invoice.note || '-'}
                        </p>
                    </div>

                    <div>
                        <p className="text-xs text-gray-500">Lampiran</p>
                        <div className="mt-1 flex items-center gap-2 text-sm text-gray-500">
                            <Paperclip className="size-4" aria-hidden="true" />
                            <span>Belum ada lampiran faktur.</span>
                        </div>
                    </div>

                    <p className="pt-2 text-xs text-gray-500">
                        Terakhir diubah {formatDateTime(invoice.updated_at)}
                    </p>
                </div>

                <dl className="space-y-2 text-sm lg:border-l lg:border-gray-200 lg:pl-6 dark:lg:border-gray-800">
                    <div className="flex justify-between gap-4 text-gray-600 dark:text-gray-400">
                        <dt>Subtotal</dt>
                        <dd>{formatCurrency(invoice.subtotal)}</dd>
                    </div>
                    <div className="flex justify-between gap-4 text-gray-600 dark:text-gray-400">
                        <dt>Pajak (PPN)</dt>
                        <dd>{formatCurrency(invoice.tax_amount)}</dd>
                    </div>
                    <div className="flex justify-between gap-4 border-t border-dashed border-gray-200 pt-2 font-semibold text-gray-900 dark:border-gray-700 dark:text-gray-100">
                        <dt>Total</dt>
                        <dd>{formatCurrency(invoice.total)}</dd>
                    </div>
                    <div className="flex justify-between gap-4 text-gray-600 dark:text-gray-400">
                        <dt>Jumlah dibayar</dt>
                        <dd>{formatCurrency(invoice.paid_amount)}</dd>
                    </div>
                    {invoice.returned_amount > 0 ? (
                        <div className="flex justify-between gap-4 text-gray-600 dark:text-gray-400">
                            <dt>Nilai retur</dt>
                            <dd>{formatCurrency(invoice.returned_amount)}</dd>
                        </div>
                    ) : null}
                    <div className="flex justify-between gap-4 border-t border-gray-200 pt-3 text-base font-bold text-gray-950 dark:border-gray-700 dark:text-gray-50">
                        <dt>Sisa tagihan</dt>
                        <dd>{formatCurrency(invoice.outstanding)}</dd>
                    </div>
                </dl>
            </div>
        </section>
    );
}

type PaymentStatus = {
    label: string;
    color: 'default' | 'success' | 'warning' | 'danger';
};

const PAYMENT_STATUS: Record<string, PaymentStatus> = {
    approved: { label: 'Dibayar', color: 'success' },
    pending: { label: 'Menunggu', color: 'warning' },
    failed: { label: 'Gagal', color: 'danger' },
};

function PaymentStatusBadge({ status }: { status: string }) {
    const style = PAYMENT_STATUS[status] ?? {
        label: status || 'Tidak dikenal',
        color: 'default' as const,
    };

    return (
        <Chip color={style.color} size="sm" variant="soft">
            {style.label}
        </Chip>
    );
}

type InvoiceTabsPanelProps = {
    invoiceId: number;
    canPay: boolean;
    payments: InvoicePayment[];
    returns: InvoiceReturn[];
};

export function InvoiceTabsPanel({
    invoiceId,
    canPay,
    payments,
    returns,
}: InvoiceTabsPanelProps) {
    const [activeTab, setActiveTab] = useState<'payments' | 'returns'>(
        'payments',
    );

    return (
        <section className="border-t border-gray-200 dark:border-gray-800">
            <div
                role="tablist"
                aria-label="Riwayat faktur"
                className="flex gap-6 border-b border-gray-200 px-5 sm:px-8 dark:border-gray-800"
            >
                <button
                    type="button"
                    role="tab"
                    aria-selected={activeTab === 'payments'}
                    aria-controls="invoice-payments-panel"
                    onClick={() => setActiveTab('payments')}
                    className={`border-b-2 px-1 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 ${
                        activeTab === 'payments'
                            ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'
                    }`}
                >
                    Pembayaran
                </button>
                <button
                    type="button"
                    role="tab"
                    aria-selected={activeTab === 'returns'}
                    aria-controls="invoice-returns-panel"
                    onClick={() => setActiveTab('returns')}
                    className={`border-b-2 px-1 py-3 text-sm font-semibold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 ${
                        activeTab === 'returns'
                            ? 'border-brand-500 text-brand-600 dark:text-brand-400'
                            : 'border-transparent text-gray-500 hover:text-gray-800 dark:hover:text-gray-200'
                    }`}
                >
                    Retur
                </button>
            </div>

            {activeTab === 'payments' ? (
                <div
                    id="invoice-payments-panel"
                    role="tabpanel"
                    className="px-5 py-5 sm:px-8"
                >
                    {payments.length === 0 ? (
                        <div className="border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-700">
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                Belum ada pembayaran untuk faktur ini.
                            </p>
                            {canPay ? (
                                <Link
                                    href={`/purchase-payments/new?createdFrom=${invoiceId}`}
                                >
                                    <Button
                                        type="button"
                                        variant="primary"
                                        className="mt-4"
                                    >
                                        Kirim pembayaran
                                    </Button>
                                </Link>
                            ) : null}
                        </div>
                    ) : (
                        <div className="overflow-x-auto border border-gray-200 dark:border-gray-800">
                            <table className="w-full min-w-[760px] text-left text-sm text-gray-800 dark:text-gray-200">
                                <thead className="border-b border-gray-200 bg-gray-50 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                    <tr>
                                        <th className="px-4 py-3">Tanggal</th>
                                        <th className="px-4 py-3">No.</th>
                                        <th className="px-4 py-3">
                                            Cara pembayaran
                                        </th>
                                        <th className="px-4 py-3">
                                            Status pembayaran
                                        </th>
                                        <th className="px-4 py-3 text-right">
                                            Jumlah
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {payments.map((payment) => (
                                        <tr
                                            key={payment.id}
                                            className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/60"
                                        >
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {formatDate(
                                                    payment.payment_date,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/purchase-payments/${payment.id}`}
                                                    className="font-semibold text-brand-600 hover:underline dark:text-brand-400"
                                                >
                                                    Purchase Payment #
                                                    {payment.number}
                                                </Link>
                                                {payment.memo ? (
                                                    <p className="mt-0.5 text-xs text-gray-500">
                                                        {payment.memo}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 text-gray-600 dark:text-gray-400">
                                                {payment.payment_method || '-'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <PaymentStatusBadge
                                                    status={payment.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold text-gray-950 dark:text-gray-50">
                                                {formatCurrency(payment.amount)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            ) : (
                <div
                    id="invoice-returns-panel"
                    role="tabpanel"
                    className="px-5 py-5 sm:px-8"
                >
                    {returns.length === 0 ? (
                        <div
                            role="status"
                            className="border border-dashed border-gray-300 px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-700"
                        >
                            Belum ada retur untuk faktur ini.
                        </div>
                    ) : (
                        <div className="overflow-x-auto border border-gray-200 dark:border-gray-800">
                            <table className="w-full min-w-[680px] text-left text-sm text-gray-800 dark:text-gray-200">
                                <thead className="border-b border-gray-200 bg-gray-50 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                    <tr>
                                        <th className="px-4 py-3">
                                            Nomor retur
                                        </th>
                                        <th className="px-4 py-3">Tanggal</th>
                                        <th className="px-4 py-3">Status</th>
                                        <th className="px-4 py-3 text-right">
                                            Nilai retur
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                    {returns.map((purchaseReturn) => (
                                        <tr
                                            key={purchaseReturn.id}
                                            className="transition-colors hover:bg-gray-50 dark:hover:bg-gray-900/60"
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/purchasing/returns/${purchaseReturn.id}`}
                                                    className="font-semibold text-brand-600 hover:underline dark:text-brand-400"
                                                >
                                                    {purchaseReturn.number}
                                                </Link>
                                                {purchaseReturn.memo ? (
                                                    <p className="mt-0.5 text-xs text-gray-500">
                                                        {purchaseReturn.memo}
                                                    </p>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap">
                                                {formatDate(
                                                    purchaseReturn.return_date,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <PurchasingStatusBadge
                                                    status={
                                                        purchaseReturn.status
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right font-semibold text-gray-950 dark:text-gray-50">
                                                {formatCurrency(
                                                    purchaseReturn.total,
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}

type InvoiceJournalModalProps = {
    invoiceNumber: string;
    journal: InvoiceJournal;
    onClose: () => void;
};

export function InvoiceJournalModal({
    invoiceNumber,
    journal,
    onClose,
}: InvoiceJournalModalProps) {
    useEffect(() => {
        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => document.removeEventListener('keydown', handleKeyDown);
    }, [onClose]);

    const totalDebit = journal.lines.reduce((sum, line) => sum + line.debit, 0);
    const totalCredit = journal.lines.reduce(
        (sum, line) => sum + line.credit,
        0,
    );

    return (
        <div
            className="fixed inset-0 z-[100000] flex items-center justify-center bg-gray-950/40 p-4"
            role="presentation"
            onClick={onClose}
        >
            <div
                role="dialog"
                aria-modal="true"
                aria-labelledby="invoice-journal-title"
                onClick={(event) => event.stopPropagation()}
                className="max-h-[calc(100vh-2rem)] w-full max-w-4xl overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-800 dark:bg-gray-950"
            >
                <div className="flex items-center justify-between border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div>
                        <h2
                            id="invoice-journal-title"
                            className="text-base font-bold text-gray-900 dark:text-gray-100"
                        >
                            Laporan Jurnal Purchase Invoice #{invoiceNumber}
                        </h2>
                        <p className="mt-1 text-xs text-gray-500">
                            {formatDate(journal.journal_date)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup jurnal"
                        className="rounded-md p-1 text-gray-500 hover:bg-gray-100 hover:text-gray-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        <X className="size-5" aria-hidden="true" />
                    </button>
                </div>

                <div className="overflow-x-auto px-5 py-4">
                    {journal.lines.length === 0 ? (
                        <p className="py-8 text-center text-sm text-gray-500">
                            Jurnal tidak memiliki baris.
                        </p>
                    ) : (
                        <table className="w-full min-w-[680px] text-left text-sm text-gray-800 dark:text-gray-200">
                            <thead className="border-b border-gray-200 bg-gray-50 text-xs font-bold text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                <tr>
                                    <th className="px-3 py-3">Akun</th>
                                    <th className="px-3 py-3 text-right">
                                        Debit
                                    </th>
                                    <th className="px-3 py-3 text-right">
                                        Kredit
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 dark:divide-gray-800">
                                {journal.lines.map((line, index) => (
                                    <tr key={`${line.account_code}-${index}`}>
                                        <td className="px-3 py-3">
                                            <span className="text-brand-600 dark:text-brand-400">
                                                {line.account_code}
                                            </span>{' '}
                                            <span>{line.account_name}</span>
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            {line.debit > 0
                                                ? formatCurrency(line.debit)
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-3 text-right">
                                            {line.credit > 0
                                                ? formatCurrency(line.credit)
                                                : '-'}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="border-t-2 border-gray-300 font-semibold dark:border-gray-700">
                                <tr>
                                    <td className="px-3 py-3">Total</td>
                                    <td className="px-3 py-3 text-right">
                                        {formatCurrency(totalDebit)}
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        {formatCurrency(totalCredit)}
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    )}
                </div>
            </div>
        </div>
    );
}
