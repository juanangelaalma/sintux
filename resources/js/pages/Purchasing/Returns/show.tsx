import { Button } from '@heroui/react';
import { Head, Link } from '@inertiajs/react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';

type ReturnLineageStep = {
    label: string;
    source_type: string | null;
    source_id: number | null;
    layer_id: number;
};

type ReturnItem = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string | null;
    qty: number;
    unit_price: number;
    tax_rate: number;
    line_total: number;
    lineage?: ReturnLineageStep[];
};

type JournalLine = {
    account_code: string;
    account_name: string;
    debit: number;
    credit: number;
    memo?: string | null;
};

type DebitMemo = {
    id: number;
    number: string;
    status: string;
    total: number;
    remaining: number;
};

type Attachment = {
    id: number;
    original_name: string;
    mime?: string | null;
    size?: number | null;
};

type ReturnTransfer = {
    id: number;
    number: string;
    status: string;
    from_warehouse_name: string;
    from_branch_name: string;
    to_warehouse_name: string;
};

type PurchaseReturnDetail = {
    return: {
        id: number;
        number: string;
        status: string;
        return_date: string;
        message?: string | null;
        memo?: string | null;
        is_tax_inclusive: boolean;
        subtotal: number;
        tax_amount: number;
        total: number;
        supplier_id: number;
        warehouse: { id: number; name: string };
    };
    invoice: { id: number; number: string; status: string };
    items: ReturnItem[];
    transfer?: ReturnTransfer | null;
    journal?: {
        id: number;
        memo?: string | null;
        journal_date: string;
        lines: JournalLine[];
    } | null;
    debitMemos: DebitMemo[];
    tags: { id: number; name: string }[];
    attachments: Attachment[];
};

type Props = {
    purchaseReturn: PurchaseReturnDetail;
    approval?: ApprovalStatusProps | null;
};

export default function PurchaseReturnsShow({
    purchaseReturn,
    approval,
}: Props) {
    const {
        return: retur,
        invoice,
        items,
        transfer,
        journal,
        debitMemos,
        tags,
        attachments,
    } = purchaseReturn;

    const rows: DetailRow[] = [
        {
            label: 'Tanggal retur',
            value: formatDate(retur.return_date),
        },
        {
            label: 'Faktur sumber',
            value: (
                <Link
                    href={`/purchasing/invoices/${invoice.id}`}
                    className="text-accent hover:underline"
                >
                    #{invoice.number}
                </Link>
            ),
        },
        {
            label: 'Gudang',
            value: retur.warehouse.name || '—',
        },
        ...(transfer
            ? [
                  {
                      label: 'Transfer retur',
                      value:
                          transfer.status === 'deleted' ? (
                              `${transfer.number}`
                          ) : (
                              <Link
                                  href={`/warehouse/stock-transfers/${transfer.id}`}
                                  className="text-accent hover:underline"
                              >
                                  {transfer.number}
                              </Link>
                          ),
                  } as DetailRow,
                  {
                      label: 'Asal transfer',
                      value:
                          transfer.status === 'deleted'
                              ? '—'
                              : `${transfer.from_warehouse_name}${transfer.from_branch_name ? ` — ${transfer.from_branch_name}` : ''} → ${transfer.to_warehouse_name} (${transfer.status})`,
                  } as DetailRow,
              ]
            : []),
        ...(tags.length > 0
            ? [
                  {
                      label: 'Tag',
                      value: tags.map((tag) => tag.name).join(', '),
                  } as DetailRow,
              ]
            : []),
    ];

    return (
        <CompanyLayout>
            <Head title={`Retur #${retur.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Retur"
                    title={`Retur Pembelian #${retur.number}`}
                    actions={
                        <>
                            <Link href={`/purchasing/invoices/${invoice.id}`}>
                                <Button type="button" variant="secondary">
                                    Kembali ke Faktur
                                </Button>
                            </Link>
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={retur.number}
                            />
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={retur.status}
                    statusLabel="Status Retur"
                    rows={rows}
                    note={retur.message ?? undefined}
                    notePosition="bottom"
                >
                    <div>
                        <h3 className="mb-3 text-sm font-bold text-foreground">
                            Item Diretur
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
                                    {items.map((item) => (
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

                    {items.some((item) => (item.lineage ?? []).length > 0) && (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Rantai Asal Stok
                            </h3>
                            <div className="space-y-3">
                                {items
                                    .filter(
                                        (item) =>
                                            (item.lineage ?? []).length > 0,
                                    )
                                    .map((item) => (
                                        <div
                                            key={`lineage-${item.id}`}
                                            className="rounded-lg border border-border p-3 text-sm"
                                        >
                                            <p className="mb-2 font-semibold text-foreground">
                                                {item.product_name}
                                                <span className="ml-2 font-mono text-xs text-muted">
                                                    {item.sku}
                                                </span>
                                            </p>
                                            <ol className="flex flex-wrap items-center gap-2">
                                                {(item.lineage ?? []).map(
                                                    (step, stepIndex) => (
                                                        <li
                                                            key={`${step.layer_id}-${stepIndex}`}
                                                            className="flex items-center gap-2"
                                                        >
                                                            {stepIndex > 0 && (
                                                                <span
                                                                    aria-hidden
                                                                    className="text-muted"
                                                                >
                                                                    →
                                                                </span>
                                                            )}
                                                            <span className="rounded bg-surface-secondary px-2 py-1 font-mono text-xs text-foreground">
                                                                {step.label}
                                                            </span>
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        </div>
                                    ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end border-t border-border/60 pt-4">
                        <div className="w-72 space-y-2 text-sm">
                            <div className="flex justify-between text-muted">
                                <span>Subtotal</span>
                                <span>{formatCurrency(retur.subtotal)}</span>
                            </div>
                            <div className="flex justify-between text-muted">
                                <span>Pajak (PPN)</span>
                                <span>{formatCurrency(retur.tax_amount)}</span>
                            </div>
                            <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                                <span>Total Retur</span>
                                <span>{formatCurrency(retur.total)}</span>
                            </div>
                        </div>
                    </div>

                    {retur.memo && (
                        <div className="rounded-lg border border-border bg-surface-secondary/40 p-3 text-sm">
                            <p className="text-xs font-semibold text-muted">
                                Memo (laporan)
                            </p>
                            <p className="mt-1 text-foreground">{retur.memo}</p>
                        </div>
                    )}

                    {journal ? (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Jurnal Retur
                            </h3>
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="w-full text-left text-sm text-foreground">
                                    <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                        <tr>
                                            <th className="px-4 py-3">Akun</th>
                                            <th className="px-4 py-3 text-right">
                                                Debit
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Kredit
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {journal.lines.map((line, index) => (
                                            <tr key={index}>
                                                <td className="px-4 py-3">
                                                    <span className="font-mono text-xs text-muted">
                                                        {line.account_code}
                                                    </span>{' '}
                                                    <span className="font-medium">
                                                        {line.account_name}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {line.debit > 0
                                                        ? formatCurrency(
                                                              line.debit,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {line.credit > 0
                                                        ? formatCurrency(
                                                              line.credit,
                                                          )
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ) : (
                        <div
                            role="status"
                            className="rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted"
                        >
                            Jurnal belum terbit — retur masih menunggu
                            persetujuan.
                        </div>
                    )}

                    {debitMemos.length > 0 && (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Debit Memo Supplier
                            </h3>
                            <div className="space-y-2">
                                {debitMemos.map((memo) => (
                                    <div
                                        key={memo.id}
                                        className="flex items-center justify-between rounded-lg border border-border p-3 text-sm"
                                    >
                                        <div>
                                            <p className="font-bold text-foreground">
                                                {memo.number}
                                            </p>
                                            <p className="text-xs text-muted">
                                                Sisa kredit:{' '}
                                                {formatCurrency(memo.remaining)}
                                            </p>
                                        </div>
                                        <p className="font-bold text-foreground">
                                            {formatCurrency(memo.total)}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {attachments.length > 0 && (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Lampiran
                            </h3>
                            <ul className="space-y-2 text-sm">
                                {attachments.map((file) => (
                                    <li key={file.id}>
                                        <a
                                            href={`/purchasing/returns/${retur.id}/attachments/${file.id}`}
                                            className="text-accent hover:underline"
                                        >
                                            {file.original_name ||
                                                `Lampiran #${file.id}`}
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </PurchaseDocumentDetail>
            </div>
        </CompanyLayout>
    );
}
