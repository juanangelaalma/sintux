import { Button, Card } from '@heroui/react';
import { Icon } from '@iconify/react';
import { Head, Link, usePage } from '@inertiajs/react';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency, formatDateDash } from '@/lib/format';
import ExpenseStatusBadge from './status-badge';
import type { ExpenseStatusValue, TagOption } from './types';

type ExpenseLine = {
    id: number;
    position: number;
    account_id: number;
    account_name: string;
    description: string | null;
    tax_id: number | null;
    tax_rate: number;
    amount: number;
    amount_before_tax: number;
    tax_amount: number;
};

type ExpenseAttachment = {
    id: number;
    original_name: string | null;
    size: number | null;
};

type ExpenseDetail = {
    id: number;
    number: string;
    transaction_date: string;
    pay_from_account_id: number | null;
    is_pay_later: boolean;
    contact_name: string | null;
    billing_address: string | null;
    payment_method_id: number | null;
    currency_code: string;
    is_tax_inclusive: boolean;
    withholding_type: string | null;
    withholding_value: number;
    withholding_total: number;
    memo: string | null;
    subtotal: number;
    tax_total: number;
    grand_total: number;
    amount_paid: number;
    status: ExpenseStatusValue;
    source: string;
    lines: ExpenseLine[];
    tags: TagOption[];
    attachments: ExpenseAttachment[];
};

type Props = {
    expense: ExpenseDetail;
};

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-0.5">
            <dt className="text-[11px] font-semibold tracking-wider text-muted uppercase">
                {label}
            </dt>
            <dd className="text-sm text-foreground">{children}</dd>
        </div>
    );
}

export default function ExpensesShow({ expense }: Props) {
    const { props } = usePage();
    const auth = (props.auth ?? {}) as { permissions?: string[] };
    const canUpdate = (auth.permissions ?? []).includes('expense.update');

    return (
        <CompanyLayout>
            <Head title={`Biaya ${expense.number}`} />

            <div className="w-full space-y-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-2">
                        <p className="text-xs font-semibold tracking-widest text-muted uppercase">
                            Biaya
                        </p>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold text-foreground">
                                {expense.number}
                            </h1>
                            <ExpenseStatusBadge status={expense.status} />
                        </div>
                        <p className="text-sm text-muted">
                            {formatDateDash(expense.transaction_date)}
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        {canUpdate && (
                            <span className="text-xs text-muted">
                                Ubah tersedia di Fase 2
                            </span>
                        )}
                        <Link href="/expenses">
                            <Button variant="secondary" className="gap-1.5">
                                <Icon
                                    className="size-4"
                                    icon="gravity-ui:arrow-left"
                                />
                                Kembali
                            </Button>
                        </Link>
                    </div>
                </div>

                <Card className="p-4">
                    <dl className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        <Field label="Penerima">
                            {expense.contact_name ?? '-'}
                        </Field>
                        <Field label="Bayar nanti">
                            {expense.is_pay_later ? 'Ya' : 'Tidak'}
                        </Field>
                        <Field label="Mata uang">{expense.currency_code}</Field>
                        <Field label="Harga termasuk pajak">
                            {expense.is_tax_inclusive ? 'Ya' : 'Tidak'}
                        </Field>
                        {expense.billing_address && (
                            <div className="col-span-2 flex flex-col gap-0.5">
                                <dt className="text-[11px] font-semibold tracking-wider text-muted uppercase">
                                    Alamat penagihan
                                </dt>
                                <dd className="text-sm whitespace-pre-line text-foreground">
                                    {expense.billing_address}
                                </dd>
                            </div>
                        )}
                        {expense.memo && (
                            <div className="col-span-2 flex flex-col gap-0.5">
                                <dt className="text-[11px] font-semibold tracking-wider text-muted uppercase">
                                    Memo
                                </dt>
                                <dd className="text-sm whitespace-pre-line text-foreground">
                                    {expense.memo}
                                </dd>
                            </div>
                        )}
                    </dl>

                    {expense.tags.length > 0 && (
                        <div className="mt-4 flex flex-wrap items-center gap-2">
                            <span className="text-[11px] font-semibold tracking-wider text-muted uppercase">
                                Tags
                            </span>
                            {expense.tags.map((tag) => (
                                <span
                                    key={tag.id}
                                    className="rounded bg-accent/10 px-2 py-0.5 text-xs text-accent"
                                >
                                    {tag.name}
                                </span>
                            ))}
                        </div>
                    )}
                </Card>

                <Card className="p-0">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-sm">
                            <thead>
                                <tr className="bg-accent/10 text-xs text-accent">
                                    <th className="px-4 py-2.5 text-start font-medium">
                                        Akun biaya
                                    </th>
                                    <th className="px-4 py-2.5 text-start font-medium">
                                        Deskripsi
                                    </th>
                                    <th className="px-4 py-2.5 text-end font-medium">
                                        Nilai sebelum pajak
                                    </th>
                                    <th className="px-4 py-2.5 text-end font-medium">
                                        Pajak
                                    </th>
                                    <th className="px-4 py-2.5 text-end font-medium">
                                        Jumlah
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {expense.lines.map((line) => (
                                    <tr
                                        key={line.id}
                                        className="border-t border-border"
                                    >
                                        <td className="px-4 py-2.5">
                                            {line.account_name}
                                        </td>
                                        <td className="px-4 py-2.5 text-muted">
                                            {line.description ?? '-'}
                                        </td>
                                        <td className="px-4 py-2.5 text-end">
                                            {formatCurrency(
                                                line.amount_before_tax,
                                            )}
                                        </td>
                                        <td className="px-4 py-2.5 text-end">
                                            {formatCurrency(line.tax_amount)}
                                        </td>
                                        <td className="px-4 py-2.5 text-end font-medium">
                                            {formatCurrency(line.amount)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-col items-end gap-1 border-t border-border px-4 py-3 text-sm">
                        <div className="flex justify-between gap-12">
                            <span className="text-muted">Subtotal</span>
                            <span>{formatCurrency(expense.subtotal)}</span>
                        </div>
                        <div className="flex justify-between gap-12">
                            <span className="text-muted">Pajak</span>
                            <span>{formatCurrency(expense.tax_total)}</span>
                        </div>
                        {expense.withholding_total > 0 && (
                            <div className="flex justify-between gap-12 text-danger">
                                <span>
                                    Pemotongan (
                                    {expense.withholding_type === 'percent'
                                        ? `${expense.withholding_value}%`
                                        : formatCurrency(
                                              expense.withholding_value,
                                          )}
                                    )
                                </span>
                                <span>
                                    -{formatCurrency(expense.withholding_total)}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between gap-12 border-t border-border pt-1 text-base font-semibold">
                            <span>Total</span>
                            <span>{formatCurrency(expense.grand_total)}</span>
                        </div>
                        {expense.is_pay_later && (
                            <div className="flex justify-between gap-12 text-muted">
                                <span>Sudah dibayar</span>
                                <span>
                                    {formatCurrency(expense.amount_paid)}
                                </span>
                            </div>
                        )}
                    </div>
                </Card>

                {expense.attachments.length > 0 && (
                    <Card className="p-4">
                        <h2 className="mb-2 text-sm font-semibold text-foreground">
                            Lampiran
                        </h2>
                        <ul className="flex flex-col gap-1 text-sm text-muted">
                            {expense.attachments.map((attachment) => (
                                <li key={attachment.id}>
                                    {attachment.original_name ?? 'Lampiran'}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>
        </CompanyLayout>
    );
}
