import { Button, Input, Label, ListBox, Select, TextArea } from '@heroui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { SupplierOption } from '@/components/purchasing/supplier-fields';
import { TagComboBox } from '@/components/ui/app-combobox/tags/tag-combobox';
import type { TagItem } from '@/components/ui/app-combobox/tags/tag-combobox';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';

type AccountOption = { id: number; code: string; name: string };
type PaymentMethodOption = { id: number; name: string; code: string };
type PayableInvoice = {
    id: number;
    number: string;
    supplier_id: number;
    total: number;
    paid_amount: number;
    returned_amount: number;
    outstanding: number;
    status: string;
};
type CreditOption = { id: number; number: string; remaining: number };

type Props = {
    mode: 'invoice' | 'deposit';
    hqBranchId: number | null;
    suppliers: SupplierOption[];
    paymentMethods: PaymentMethodOption[];
    accounts: AccountOption[];
    tags: TagItem[];
    supplierId: number;
    payableInvoices: PayableInvoice[];
    supplierDeposits: CreditOption[];
    supplierMemos: CreditOption[];
    prefillAllocation: { purchase_invoice_id: number; amount: number } | null;
};

type AllocationLine = { purchase_invoice_id: number; amount: number };
type CreditUse = { id: number; amount: number };
type WithholdingLine = {
    account_id: number | '';
    type: 'percent' | 'nominal';
    value: number;
};

export default function PurchasePaymentsCreate({
    mode: initialMode,
    hqBranchId,
    suppliers,
    paymentMethods,
    accounts,
    tags,
    supplierId: initialSupplierId,
    payableInvoices,
    supplierDeposits,
    supplierMemos,
    prefillAllocation,
}: Props) {
    const today = useMemo(() => new Date().toISOString().split('T')[0], []);
    const [mode, setMode] = useState<'invoice' | 'deposit'>(initialMode);
    // Tag dipilih dari master yang ada; form pembayaran tidak membuat tag baru.
    const allTags = useMemo(() => tags, [tags]);

    // Akun kas/bank: kode 11xx (Kas Kecil, Bank, dst). Toggle menampilkan semua.
    const cashAccounts = useMemo(
        () => accounts.filter((a) => a.code.startsWith('11')),
        [accounts],
    );
    const [showAllAccounts, setShowAllAccounts] = useState(false);
    const accountOptions = showAllAccounts ? accounts : cashAccounts;

    const initialAllocations: AllocationLine[] = prefillAllocation
        ? [prefillAllocation]
        : payableInvoices.map((invoice) => ({
              purchase_invoice_id: invoice.id,
              amount: invoice.outstanding,
          }));

    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        payment_method_id: number | '';
        cash_account_id: number | '';
        mode: 'invoice' | 'deposit';
        amount: number | '';
        payment_date: string;
        due_date: string;
        currency_code: string;
        memo: string;
        tag_ids: number[];
        allocations: AllocationLine[];
        withholdings: WithholdingLine[];
        deposit_uses: CreditUse[];
        memo_uses: CreditUse[];
    }>({
        branch_id: hqBranchId ?? '',
        supplier_id: initialSupplierId,
        payment_method_id: paymentMethods[0]?.id ?? '',
        cash_account_id: cashAccounts[0]?.id ?? '',
        mode: initialMode,
        amount: '',
        payment_date: today,
        due_date: '',
        currency_code: 'IDR',
        memo: '',
        tag_ids: [],
        allocations: initialAllocations,
        withholdings: [],
        deposit_uses: [],
        memo_uses: [],
    });

    const supplierName =
        suppliers.find((s) => s.id === Number(data.supplier_id))?.name ?? '';

    const gross = useMemo(
        () =>
            data.allocations.reduce(
                (sum, line) => sum + (Number(line.amount) || 0),
                0,
            ),
        [data.allocations],
    );

    const withholdingTotal = useMemo(
        () =>
            data.withholdings.reduce((sum, w) => {
                const value = Number(w.value) || 0;

                return (
                    sum + (w.type === 'percent' ? (gross * value) / 100 : value)
                );
            }, 0),
        [data.withholdings, gross],
    );

    const creditTotal = useMemo(
        () =>
            data.deposit_uses.reduce(
                (sum, u) => sum + (Number(u.amount) || 0),
                0,
            ) +
            data.memo_uses.reduce((sum, u) => sum + (Number(u.amount) || 0), 0),
        [data.deposit_uses, data.memo_uses],
    );

    const cashOut = Math.max(0, gross - withholdingTotal - creditTotal);

    const changeSupplier = (supplierId: string) => {
        setData('supplier_id', supplierId);
        // Muat ulang daftar faktur & kredit milik supplier terpilih.
        router.get(
            '/purchase-payments/new',
            { mode, supplier_id: supplierId },
            { preserveState: true, preserveScroll: true },
        );
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchase-payments');
    };

    return (
        <CompanyLayout>
            <Head title="Kirim Pembayaran" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Kirim Pembayaran
                        </h1>
                        {supplierName && (
                            <p className="mt-1 text-sm text-muted">
                                Supplier: {supplierName}
                            </p>
                        )}
                    </div>
                    <div className="text-right">
                        <p className="text-xs font-medium text-muted">
                            Total Keluar Kas
                        </p>
                        <p className="mt-0.5 text-2xl font-black text-foreground">
                            {formatCurrency(cashOut)}
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Supplier *
                            </Label>
                            <Select
                                fullWidth
                                placeholder="Pilih supplier..."
                                value={
                                    data.supplier_id
                                        ? String(data.supplier_id)
                                        : ''
                                }
                                onChange={(val) => changeSupplier(String(val))}
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        {suppliers.map((supplier) => (
                                            <ListBox.Item
                                                key={supplier.id}
                                                id={String(supplier.id)}
                                                textValue={supplier.name}
                                            >
                                                {supplier.name}
                                            </ListBox.Item>
                                        ))}
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                            {errors.supplier_id && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.supplier_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Cara Pembayaran
                            </Label>
                            <Select
                                fullWidth
                                placeholder="Pilih cara..."
                                value={
                                    data.payment_method_id
                                        ? String(data.payment_method_id)
                                        : ''
                                }
                                onChange={(val) =>
                                    setData(
                                        'payment_method_id',
                                        val ? Number(val) : '',
                                    )
                                }
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        {paymentMethods.map((method) => (
                                            <ListBox.Item
                                                key={method.id}
                                                id={String(method.id)}
                                                textValue={method.name}
                                            >
                                                {method.name}
                                            </ListBox.Item>
                                        ))}
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Bayar Dari *
                            </Label>
                            <Select
                                fullWidth
                                placeholder="Pilih akun kas..."
                                value={
                                    data.cash_account_id
                                        ? String(data.cash_account_id)
                                        : ''
                                }
                                onChange={(val) =>
                                    setData(
                                        'cash_account_id',
                                        val ? Number(val) : '',
                                    )
                                }
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        {accountOptions.map((account) => (
                                            <ListBox.Item
                                                key={account.id}
                                                id={String(account.id)}
                                                textValue={`${account.code} ${account.name}`}
                                            >
                                                {account.code} - {account.name}
                                            </ListBox.Item>
                                        ))}
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                            {errors.cash_account_id && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.cash_account_id}
                                </p>
                            )}
                            <label className="mt-1 flex items-center gap-2 text-xs text-muted">
                                <input
                                    type="checkbox"
                                    checked={showAllAccounts}
                                    onChange={(e) =>
                                        setShowAllAccounts(e.target.checked)
                                    }
                                />
                                Tampilkan semua akun
                            </label>
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Tgl Pembayaran *
                            </Label>
                            <Input
                                type="date"
                                max={today}
                                value={data.payment_date}
                                onChange={(e) =>
                                    setData('payment_date', e.target.value)
                                }
                                className="mt-1"
                            />
                            {errors.payment_date && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.payment_date}
                                </p>
                            )}
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Tgl Jatuh Tempo
                            </Label>
                            <Input
                                type="date"
                                value={data.due_date}
                                onChange={(e) =>
                                    setData('due_date', e.target.value)
                                }
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Jenis Transaksi
                            </Label>
                            <Select
                                fullWidth
                                value={mode}
                                onChange={(val) => {
                                    const next = val as 'invoice' | 'deposit';
                                    setMode(next);
                                    setData('mode', next);
                                }}
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        <ListBox.Item
                                            id="invoice"
                                            textValue="Pembayaran Faktur"
                                        >
                                            Pembayaran Faktur
                                        </ListBox.Item>
                                        <ListBox.Item
                                            id="deposit"
                                            textValue="Uang Muka"
                                        >
                                            Uang Muka
                                        </ListBox.Item>
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                        </div>

                        {mode === 'deposit' && (
                            <div>
                                <Label className="block text-xs font-semibold text-foreground">
                                    Nominal Uang Muka *
                                </Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step="any"
                                    value={String(data.amount ?? '')}
                                    onChange={(e) =>
                                        setData(
                                            'amount',
                                            Number(e.target.value),
                                        )
                                    }
                                    className="mt-1"
                                />
                                {errors.amount && (
                                    <p className="mt-1 text-xs text-danger">
                                        {errors.amount}
                                    </p>
                                )}
                            </div>
                        )}

                        <div>
                            <TagComboBox
                                tags={allTags}
                                selectedTagIds={data.tag_ids}
                                onSelectedTagsChange={(tagIds) =>
                                    setData('tag_ids', tagIds.map(Number))
                                }
                                placeholder="Pilih atau cari tag"
                            />
                        </div>
                    </div>

                    {mode === 'invoice' && (
                        <>
                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <h3 className="text-sm font-bold text-foreground">
                                        Alokasi Faktur
                                    </h3>
                                    <span className="text-xs text-muted">
                                        Total: {formatCurrency(gross)}
                                    </span>
                                </div>

                                {payableInvoices.length === 0 ? (
                                    <p className="rounded-lg border border-dashed border-border p-4 text-sm text-muted">
                                        Supplier ini tidak punya faktur yang
                                        bisa dibayar. Pilih supplier lain atau
                                        buat uang muka.
                                    </p>
                                ) : (
                                    <div className="overflow-hidden rounded-lg border border-border">
                                        <table className="w-full text-left text-sm text-foreground">
                                            <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                                <tr>
                                                    <th className="px-4 py-3">
                                                        Faktur
                                                    </th>
                                                    <th className="px-4 py-3 text-right">
                                                        Sisa Tagihan
                                                    </th>
                                                    <th className="px-4 py-3 text-right">
                                                        Dibayar
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-border/60">
                                                {payableInvoices.map(
                                                    (invoice) => {
                                                        const lineIndex =
                                                            data.allocations.findIndex(
                                                                (l) =>
                                                                    l.purchase_invoice_id ===
                                                                    invoice.id,
                                                            );
                                                        const line =
                                                            lineIndex >= 0
                                                                ? data
                                                                      .allocations[
                                                                      lineIndex
                                                                  ]
                                                                : null;

                                                        return (
                                                            <tr
                                                                key={invoice.id}
                                                            >
                                                                <td className="px-4 py-3 font-medium">
                                                                    {
                                                                        invoice.number
                                                                    }
                                                                </td>
                                                                <td className="px-4 py-3 text-right text-muted">
                                                                    {formatCurrency(
                                                                        invoice.outstanding,
                                                                    )}
                                                                </td>
                                                                <td className="px-4 py-3 text-right">
                                                                    <Input
                                                                        type="number"
                                                                        min={0}
                                                                        max={
                                                                            invoice.outstanding
                                                                        }
                                                                        step="any"
                                                                        value={String(
                                                                            line?.amount ??
                                                                                0,
                                                                        )}
                                                                        onChange={(
                                                                            e,
                                                                        ) => {
                                                                            const next =
                                                                                [
                                                                                    ...data.allocations,
                                                                                ];
                                                                            const amount =
                                                                                Number(
                                                                                    e
                                                                                        .target
                                                                                        .value,
                                                                                ) ||
                                                                                0;

                                                                            if (
                                                                                lineIndex >=
                                                                                0
                                                                            ) {
                                                                                next[
                                                                                    lineIndex
                                                                                ] =
                                                                                    {
                                                                                        ...next[
                                                                                            lineIndex
                                                                                        ],
                                                                                        amount,
                                                                                    };
                                                                            } else {
                                                                                next.push(
                                                                                    {
                                                                                        purchase_invoice_id:
                                                                                            invoice.id,
                                                                                        amount,
                                                                                    },
                                                                                );
                                                                            }

                                                                            setData(
                                                                                'allocations',
                                                                                next.filter(
                                                                                    (
                                                                                        l,
                                                                                    ) =>
                                                                                        l.amount >
                                                                                        0,
                                                                                ),
                                                                            );
                                                                        }}
                                                                        className="ml-auto w-32 text-right"
                                                                        aria-label={`Nominal bayar faktur ${invoice.number}`}
                                                                    />
                                                                </td>
                                                            </tr>
                                                        );
                                                    },
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {errors.allocations && (
                                    <p className="mt-1 text-xs text-danger">
                                        {errors.allocations}
                                    </p>
                                )}
                            </div>

                            <div>
                                <div className="mb-2 flex items-center justify-between">
                                    <h3 className="text-sm font-bold text-foreground">
                                        Pemotongan
                                    </h3>
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        onPress={() =>
                                            setData('withholdings', [
                                                ...data.withholdings,
                                                {
                                                    account_id: '',
                                                    type: 'percent',
                                                    value: 0,
                                                },
                                            ])
                                        }
                                    >
                                        Tambah Pemotongan
                                    </Button>
                                </div>

                                {data.withholdings.length === 0 ? (
                                    <p className="text-xs text-muted">
                                        Tidak ada pemotongan. Nominal penuh
                                        keluar dari akun kas.
                                    </p>
                                ) : (
                                    <div className="space-y-2">
                                        {data.withholdings.map(
                                            (withholding, index) => (
                                                <div
                                                    key={index}
                                                    className="grid grid-cols-1 gap-2 rounded-lg border border-border p-3 md:grid-cols-[1fr_8rem_8rem_auto]"
                                                >
                                                    <Select
                                                        fullWidth
                                                        placeholder="Akun pemotongan"
                                                        value={
                                                            withholding.account_id
                                                                ? String(
                                                                      withholding.account_id,
                                                                  )
                                                                : ''
                                                        }
                                                        onChange={(val) => {
                                                            const next = [
                                                                ...data.withholdings,
                                                            ];
                                                            next[index] = {
                                                                ...next[index],
                                                                account_id: val
                                                                    ? Number(
                                                                          val,
                                                                      )
                                                                    : '',
                                                            };
                                                            setData(
                                                                'withholdings',
                                                                next,
                                                            );
                                                        }}
                                                    >
                                                        <Select.Trigger>
                                                            <Select.Value />
                                                            <Select.Indicator />
                                                        </Select.Trigger>
                                                        <Select.Popover>
                                                            <ListBox>
                                                                {accounts.map(
                                                                    (
                                                                        account,
                                                                    ) => (
                                                                        <ListBox.Item
                                                                            key={
                                                                                account.id
                                                                            }
                                                                            id={String(
                                                                                account.id,
                                                                            )}
                                                                            textValue={`${account.code} ${account.name}`}
                                                                        >
                                                                            {
                                                                                account.code
                                                                            }{' '}
                                                                            -{' '}
                                                                            {
                                                                                account.name
                                                                            }
                                                                        </ListBox.Item>
                                                                    ),
                                                                )}
                                                            </ListBox>
                                                        </Select.Popover>
                                                    </Select>

                                                    <Select
                                                        fullWidth
                                                        value={withholding.type}
                                                        onChange={(val) => {
                                                            const next = [
                                                                ...data.withholdings,
                                                            ];
                                                            next[index] = {
                                                                ...next[index],
                                                                type: val as
                                                                    | 'percent'
                                                                    | 'nominal',
                                                            };
                                                            setData(
                                                                'withholdings',
                                                                next,
                                                            );
                                                        }}
                                                    >
                                                        <Select.Trigger>
                                                            <Select.Value />
                                                            <Select.Indicator />
                                                        </Select.Trigger>
                                                        <Select.Popover>
                                                            <ListBox>
                                                                <ListBox.Item
                                                                    id="percent"
                                                                    textValue="Persen"
                                                                >
                                                                    Persen
                                                                </ListBox.Item>
                                                                <ListBox.Item
                                                                    id="nominal"
                                                                    textValue="Nominal"
                                                                >
                                                                    Nominal
                                                                </ListBox.Item>
                                                            </ListBox>
                                                        </Select.Popover>
                                                    </Select>

                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="any"
                                                        value={String(
                                                            withholding.value,
                                                        )}
                                                        onChange={(e) => {
                                                            const next = [
                                                                ...data.withholdings,
                                                            ];
                                                            next[index] = {
                                                                ...next[index],
                                                                value:
                                                                    Number(
                                                                        e.target
                                                                            .value,
                                                                    ) || 0,
                                                            };
                                                            setData(
                                                                'withholdings',
                                                                next,
                                                            );
                                                        }}
                                                        aria-label="Nilai pemotongan"
                                                    />

                                                    <Button
                                                        type="button"
                                                        variant="danger"
                                                        size="sm"
                                                        onPress={() =>
                                                            setData(
                                                                'withholdings',
                                                                data.withholdings.filter(
                                                                    (_, i) =>
                                                                        i !==
                                                                        index,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Hapus
                                                    </Button>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                                {errors.withholdings && (
                                    <p className="mt-1 text-xs text-danger">
                                        {errors.withholdings}
                                    </p>
                                )}
                            </div>

                            {(supplierDeposits.length > 0 ||
                                supplierMemos.length > 0) && (
                                <div>
                                    <h3 className="mb-2 text-sm font-bold text-foreground">
                                        Kredit Supplier
                                    </h3>
                                    <div className="space-y-3">
                                        {supplierDeposits.map((deposit) => (
                                            <CreditRow
                                                key={`deposit-${deposit.id}`}
                                                label={`Uang Muka ${deposit.number}`}
                                                remaining={deposit.remaining}
                                                value={data.deposit_uses.find(
                                                    (u) => u.id === deposit.id,
                                                )}
                                                onChange={(amount) => {
                                                    const next =
                                                        data.deposit_uses.filter(
                                                            (u) =>
                                                                u.id !==
                                                                deposit.id,
                                                        );

                                                    if (amount > 0) {
                                                        next.push({
                                                            id: deposit.id,
                                                            amount,
                                                        });
                                                    }

                                                    setData(
                                                        'deposit_uses',
                                                        next,
                                                    );
                                                }}
                                            />
                                        ))}
                                        {supplierMemos.map((memo) => (
                                            <CreditRow
                                                key={`memo-${memo.id}`}
                                                label={`Debit Memo ${memo.number}`}
                                                remaining={memo.remaining}
                                                value={data.memo_uses.find(
                                                    (u) => u.id === memo.id,
                                                )}
                                                onChange={(amount) => {
                                                    const next =
                                                        data.memo_uses.filter(
                                                            (u) =>
                                                                u.id !==
                                                                memo.id,
                                                        );

                                                    if (amount > 0) {
                                                        next.push({
                                                            id: memo.id,
                                                            amount,
                                                        });
                                                    }

                                                    setData('memo_uses', next);
                                                }}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </>
                    )}

                    <div>
                        <Label className="block text-xs font-semibold text-foreground">
                            Memo
                        </Label>
                        <TextArea
                            value={data.memo}
                            onChange={(e) => setData('memo', e.target.value)}
                            placeholder="Catatan internal..."
                            className="mt-1"
                        />
                    </div>

                    <div className="flex justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/purchasing/invoices">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing}
                        >
                            Buat Pembayaran
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}

function CreditRow({
    label,
    remaining,
    value,
    onChange,
}: {
    label: string;
    remaining: number;
    value?: { amount: number };
    onChange: (amount: number) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-border p-3 text-sm">
            <div>
                <p className="font-medium text-foreground">{label}</p>
                <p className="text-xs text-muted">
                    Sisa: {formatCurrency(remaining)}
                </p>
            </div>
            <Input
                type="number"
                min={0}
                max={remaining}
                step="any"
                value={String(value?.amount ?? 0)}
                onChange={(e) => onChange(Number(e.target.value) || 0)}
                className="w-32 text-right"
                aria-label={`Pakai ${label}`}
            />
        </div>
    );
}
