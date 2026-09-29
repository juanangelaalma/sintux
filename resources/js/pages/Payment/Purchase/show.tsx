import { Button } from '@heroui/react';
import { Head, Link } from '@inertiajs/react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import PurchaseDocumentDetail from '@/components/purchasing/purchase-document-detail';
import type { DetailRow } from '@/components/purchasing/purchase-document-detail';
import PurchaseDocumentHeader from '@/components/purchasing/purchase-document-header';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDate } from '@/lib/format';

type JournalLine = {
    account_code: string;
    account_name: string;
    debit: number;
    credit: number;
};

type PaymentDetail = {
    payment: {
        id: number;
        number: string;
        status: string;
        mode: string;
        payment_date: string;
        due_date: string | null;
        currency_code: string;
        supplier_id: number;
        payment_method: string | null;
        gross_amount: number;
        withholding_amount: number;
        deposit_applied: number;
        memo_applied: number;
        cash_out: number;
        deposit_total: number;
        deposit_remaining: number;
        failure_reason: string | null;
        memo: string | null;
    };
    allocations: { purchase_invoice_id: number; amount: number }[];
    withholdings: {
        account_id: number;
        type: string;
        value: number;
        amount: number;
    }[];
    deposit_uses: { payment_id: number; number: string; amount: number }[];
    memo_uses: { memo_id: number; amount: number }[];
    tags: { id: number; name: string }[];
    journal: {
        id: number;
        journal_date: string;
        lines: JournalLine[];
    } | null;
};

type Props = {
    payment: PaymentDetail;
    approval?: ApprovalStatusProps | null;
};

export default function PurchasePaymentsShow({ payment, approval }: Props) {
    const {
        payment: header,
        allocations,
        withholdings,
        deposit_uses,
        memo_uses,
        tags,
        journal,
    } = payment;

    const isDeposit = header.mode === 'deposit';

    const rows: DetailRow[] = [
        { label: 'Tgl Pembayaran', value: formatDate(header.payment_date) },
        ...(header.due_date
            ? [
                  {
                      label: 'Tgl Jatuh Tempo',
                      value: formatDate(header.due_date),
                  } as DetailRow,
              ]
            : []),
        ...(header.payment_method
            ? [
                  {
                      label: 'Cara Pembayaran',
                      value: header.payment_method,
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
            <Head title={`Pembayaran ${header.number}`} />
            <div className="w-full space-y-6">
                <PurchaseDocumentHeader
                    eyebrow="Pembelian / Detail Pembayaran"
                    title={`Pembayaran #${header.number}`}
                    actions={
                        <>
                            <Link href="/purchasing/invoices">
                                <Button type="button" variant="secondary">
                                    Kembali ke Faktur
                                </Button>
                            </Link>
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={header.number}
                            />
                        </>
                    }
                />

                <PurchaseDocumentDetail
                    status={header.status}
                    statusLabel="Status Pembayaran"
                    rows={rows}
                    note={header.memo ?? undefined}
                    notePosition="bottom"
                >
                    {isDeposit ? (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Uang Muka
                            </h3>
                            <div className="rounded-lg border border-border p-4 text-sm">
                                <p className="text-muted">Nominal uang muka</p>
                                <p className="mt-1 text-xl font-bold text-foreground">
                                    {formatCurrency(header.deposit_total)}
                                </p>
                                <p className="mt-2 text-xs text-muted">
                                    Sisa belum terpakai:{' '}
                                    {formatCurrency(header.deposit_remaining)}
                                </p>
                            </div>
                        </div>
                    ) : (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Alokasi Faktur
                            </h3>
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="w-full text-left text-sm text-foreground">
                                    <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Faktur
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Nominal
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {allocations.map((allocation) => (
                                            <tr
                                                key={
                                                    allocation.purchase_invoice_id
                                                }
                                            >
                                                <td className="px-4 py-3 font-medium">
                                                    Faktur #
                                                    {
                                                        allocation.purchase_invoice_id
                                                    }
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium">
                                                    {formatCurrency(
                                                        allocation.amount,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    )}

                    {withholdings.length > 0 && (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Pemotongan
                            </h3>
                            <div className="space-y-2">
                                {withholdings.map((withholding, index) => (
                                    <div
                                        key={`${withholding.account_id}-${index}`}
                                        className="flex items-center justify-between rounded-lg border border-border p-3 text-sm"
                                    >
                                        <span className="text-foreground">
                                            Akun {withholding.account_id} (
                                            {withholding.type === 'percent'
                                                ? `${withholding.value}%`
                                                : 'nominal'}
                                            )
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {formatCurrency(withholding.amount)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    {(deposit_uses.length > 0 || memo_uses.length > 0) && (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Kredit Supplier Terpakai
                            </h3>
                            <div className="space-y-2">
                                {deposit_uses.map((use) => (
                                    <div
                                        key={`deposit-${use.payment_id}`}
                                        className="flex items-center justify-between rounded-lg border border-border p-3 text-sm"
                                    >
                                        <span className="text-foreground">
                                            Uang Muka {use.number}
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {formatCurrency(use.amount)}
                                        </span>
                                    </div>
                                ))}
                                {memo_uses.map((use) => (
                                    <div
                                        key={`memo-${use.memo_id}`}
                                        className="flex items-center justify-between rounded-lg border border-border p-3 text-sm"
                                    >
                                        <span className="text-foreground">
                                            Debit Memo #{use.memo_id}
                                        </span>
                                        <span className="font-medium text-foreground">
                                            {formatCurrency(use.amount)}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end border-t border-border/60 pt-4">
                        <div className="w-80 space-y-2 text-sm">
                            <div className="flex justify-between text-muted">
                                <span>Total Tagihan</span>
                                <span>
                                    {formatCurrency(
                                        isDeposit
                                            ? header.deposit_total
                                            : header.gross_amount,
                                    )}
                                </span>
                            </div>
                            {header.withholding_amount > 0 && (
                                <div className="flex justify-between text-muted">
                                    <span>Pemotongan</span>
                                    <span>
                                        {formatCurrency(
                                            header.withholding_amount,
                                        )}
                                    </span>
                                </div>
                            )}
                            {header.deposit_applied > 0 && (
                                <div className="flex justify-between text-muted">
                                    <span>Uang Muka Dipakai</span>
                                    <span>
                                        {formatCurrency(header.deposit_applied)}
                                    </span>
                                </div>
                            )}
                            {header.memo_applied > 0 && (
                                <div className="flex justify-between text-muted">
                                    <span>Debit Memo Dipakai</span>
                                    <span>
                                        {formatCurrency(header.memo_applied)}
                                    </span>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-border pt-2 text-lg font-bold text-foreground">
                                <span>Keluar Kas</span>
                                <span>{formatCurrency(header.cash_out)}</span>
                            </div>
                        </div>
                    </div>

                    {header.failure_reason && (
                        <div
                            role="status"
                            className="rounded-lg border border-rose-500/40 bg-rose-500/10 p-4 text-sm text-rose-700 dark:text-rose-300"
                        >
                            <p className="font-semibold">
                                Pembayaran gagal difinalisasi
                            </p>
                            <ul className="mt-1 list-disc pl-5">
                                {header.failure_reason
                                    .split('\n')
                                    .filter(Boolean)
                                    .map((reason) => (
                                        <li key={reason}>{reason}</li>
                                    ))}
                            </ul>
                        </div>
                    )}

                    {journal ? (
                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Jurnal Pembayaran
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
                                                        : '-'}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {line.credit > 0
                                                        ? formatCurrency(
                                                              line.credit,
                                                          )
                                                        : '-'}
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
                            Jurnal belum terbit. Pembayaran masih menunggu
                            persetujuan.
                        </div>
                    )}
                </PurchaseDocumentDetail>
            </div>
        </CompanyLayout>
    );
}
