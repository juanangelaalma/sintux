import {
    Button,
    Input,
    Label,
    ListBox,
    NumberField,
    Select,
    Switch,
    TextArea,
} from '@heroui/react';
import type { Key } from '@heroui/react';
import { Icon } from '@iconify/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import FormDatePicker from '@/components/ui/form-date-picker';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';
import ExpenseItemsEditor from './expense-items-editor';
import { calculateExpenseTotals } from './totals';
import type {
    AccountOption,
    ContactOption,
    ExpenseLineRow,
    PaymentMethodOption,
    TagOption,
    TaxOption,
    WithholdingRow,
} from './types';

type Props = {
    activeBranch?: { id: number; name: string } | null;
    cashAccounts: AccountOption[];
    expenseAccounts: AccountOption[];
    taxes: TaxOption[];
    paymentMethods: PaymentMethodOption[];
    contacts: ContactOption[];
    tags: TagOption[];
};

const today = new Date().toISOString().slice(0, 10);

const blankLine = (): ExpenseLineRow => ({
    account_id: '',
    description: '',
    tax_id: null,
    amount: 0,
});

const blankWithholding = (): WithholdingRow => ({
    type: '',
    value: 0,
    account_id: null,
});

export default function ExpensesCreate({
    cashAccounts,
    expenseAccounts,
    taxes,
    paymentMethods,
    contacts,
    tags,
}: Props) {
    const [tagOptions, setTagOptions] = useState(tags);
    const [newTag, setNewTag] = useState('');
    const [tagError, setTagError] = useState<string | null>(null);
    const [isCreatingTag, setIsCreatingTag] = useState(false);
    const [showWithholding, setShowWithholding] = useState(false);

    const { data, setData, post, processing, errors } = useForm<{
        pay_from_account_id: number | string;
        is_pay_later: boolean;
        contact_id: number | string | '';
        transaction_date: string;
        payment_method_id: number | string;
        number: string;
        tag_ids: (number | string)[];
        billing_address: string;
        is_tax_inclusive: boolean;
        memo: string;
        lines: ExpenseLineRow[];
        withholding: {
            type: string;
            value: number;
            account_id: number | string | null;
        } | null;
        attachments: File[];
    }>({
        // PRD §7.2: default "(1-10001) - Kas (Cash & Bank)".
        pay_from_account_id: cashAccounts[0]?.id ?? '',
        is_pay_later: false,
        contact_id: '',
        transaction_date: today,
        // Default "Cek & Giro" (kolom 5, terkonfirmasi di S5).
        payment_method_id:
            paymentMethods.find((method) => method.code === 'cheque')?.id ??
            paymentMethods[0]?.id ??
            '',
        number: '',
        tag_ids: [],
        billing_address: '',
        is_tax_inclusive: false,
        memo: '',
        lines: [blankLine(), blankLine()],
        withholding: null,
        attachments: [],
    });

    const withholding: WithholdingRow = {
        type: (data.withholding?.type ?? '') as WithholdingRow['type'],
        value: Number(data.withholding?.value ?? 0),
        account_id: data.withholding?.account_id ?? null,
    };

    const totals = calculateExpenseTotals(
        data.lines,
        taxes,
        withholding,
        data.is_tax_inclusive,
    );

    const setLine = (
        index: number,
        field: keyof ExpenseLineRow,
        value: number | string | null,
    ) => {
        setData((prev) => {
            const next = [...prev.lines];
            next[index] = { ...next[index], [field]: value };

            return { ...prev, lines: next };
        });
    };

    const addLine = () => {
        setData((prev) => ({ ...prev, lines: [...prev.lines, blankLine()] }));
    };

    const removeLine = (index: number) => {
        setData((prev) => {
            if (prev.lines.length === 1) {
                return prev;
            }

            const next = [...prev.lines];
            next.splice(index, 1);

            return { ...prev, lines: next };
        });
    };

    const toggleWithholding = () => {
        const next = !showWithholding;

        setShowWithholding(next);
        setData((prev) => ({
            ...prev,
            withholding: next ? blankWithholding() : null,
        }));
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        post('/expenses');
    };

    /**
     * Kolom 7 PRD: "Multi-select + tambah baru (ketik langsung)". Tag baru
     * dibuat lewat endpoint JSON Expense lalu langsung terpilih, tanpa
     * reload halaman.
     */
    const createTag = async () => {
        const name = newTag.trim();

        if (name === '' || isCreatingTag) {
            return;
        }

        setIsCreatingTag(true);
        setTagError(null);

        try {
            const response = await fetch('/expenses/tags', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ name }),
            });

            if (!response.ok) {
                throw new Error('Gagal membuat tag');
            }

            const tag = (await response.json()) as {
                id: number;
                name: string;
                color: string | null;
            };

            setTagOptions((prev) =>
                prev.some((option) => option.id === tag.id)
                    ? prev
                    : [...prev, tag].sort((a, b) =>
                          a.name.localeCompare(b.name),
                      ),
            );
            setData((prev) => ({
                ...prev,
                tag_ids: [...prev.tag_ids, String(tag.id)],
            }));
            setNewTag('');
        } catch {
            setTagError('Tag gagal dibuat. Coba lagi.');
        } finally {
            setIsCreatingTag(false);
        }
    };

    return (
        <CompanyLayout>
            <Head title="Buat biaya" />

            <form
                onSubmit={submit}
                className="w-full space-y-6"
                encType="multipart/form-data"
            >
                <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Buat biaya
                    </h1>
                    <span className="text-sm font-medium text-accent">
                        Total{' '}
                        <span className="text-lg font-bold">
                            {formatCurrency(totals.grandTotal)}
                        </span>
                    </span>
                </div>

                <div className="flex flex-col gap-4 rounded-xl border border-border bg-accent/5 p-4 sm:flex-row sm:items-end">
                    <div className="w-full sm:w-64">
                        <Select
                            aria-label="Bayar dari"
                            placeholder="Pilih akun kas atau bank"
                            isInvalid={Boolean(errors.pay_from_account_id)}
                            selectedKey={
                                data.pay_from_account_id === ''
                                    ? null
                                    : String(data.pay_from_account_id)
                            }
                            onSelectionChange={(key) =>
                                setData(
                                    'pay_from_account_id',
                                    key === null ? '' : String(key),
                                )
                            }
                        >
                            <Label className="mb-1 block text-xs font-semibold text-foreground">
                                Bayar dari
                            </Label>
                            <Select.Trigger>
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox>
                                    {cashAccounts.map((account) => (
                                        <ListBox.Item
                                            key={String(account.id)}
                                            id={String(account.id)}
                                            textValue={`${account.code} ${account.name}`}
                                        >
                                            {account.code} - {account.name}
                                            <ListBox.ItemIndicator />
                                        </ListBox.Item>
                                    ))}
                                </ListBox>
                            </Select.Popover>
                        </Select>
                    </div>

                    <div className="flex items-center pb-2.5">
                        <Switch
                            isSelected={data.is_pay_later}
                            onChange={(value) => setData('is_pay_later', value)}
                            aria-label="Bayar nanti"
                        >
                            <Label className="text-xs font-semibold text-foreground">
                                Bayar nanti
                            </Label>
                        </Switch>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-5">
                    <div>
                        <Select
                            aria-label="Penerima"
                            placeholder="Pilih kontak"
                            selectedKey={
                                data.contact_id === ''
                                    ? null
                                    : String(data.contact_id)
                            }
                            onSelectionChange={(key) =>
                                setData(
                                    'contact_id',
                                    key === null ? '' : String(key),
                                )
                            }
                        >
                            <Label className="mb-1 block text-xs font-semibold text-foreground">
                                Penerima
                            </Label>
                            <Select.Trigger>
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox>
                                    {contacts.map((contact) => (
                                        <ListBox.Item
                                            key={String(contact.id)}
                                            id={String(contact.id)}
                                            textValue={contact.name}
                                        >
                                            {contact.name}
                                            <ListBox.ItemIndicator />
                                        </ListBox.Item>
                                    ))}
                                </ListBox>
                            </Select.Popover>
                        </Select>
                        {errors.contact_id && (
                            <p className="mt-1 text-xs text-danger">
                                {errors.contact_id}
                            </p>
                        )}
                    </div>

                    <FormDatePicker
                        label="Tgl transaksi"
                        value={data.transaction_date}
                        onChange={(value) => setData('transaction_date', value)}
                        error={errors.transaction_date}
                    />

                    <div>
                        <Select
                            aria-label="Cara pembayaran"
                            placeholder="Pilih cara pembayaran"
                            isRequired
                            isInvalid={Boolean(errors.payment_method_id)}
                            selectedKey={String(data.payment_method_id)}
                            onSelectionChange={(key) =>
                                setData(
                                    'payment_method_id',
                                    key === null ? '' : String(key),
                                )
                            }
                        >
                            <Label className="mb-1 block text-xs font-semibold text-foreground">
                                Cara pembayaran
                            </Label>
                            <Select.Trigger>
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox>
                                    {paymentMethods.map((method) => (
                                        <ListBox.Item
                                            key={String(method.id)}
                                            id={String(method.id)}
                                            textValue={method.name}
                                        >
                                            {method.name}
                                            <ListBox.ItemIndicator />
                                        </ListBox.Item>
                                    ))}
                                </ListBox>
                            </Select.Popover>
                        </Select>
                    </div>

                    <div>
                        <Label className="mb-1 block text-xs font-semibold text-foreground">
                            No biaya
                        </Label>
                        <Input
                            aria-label="No biaya"
                            placeholder="[Auto]"
                            value={data.number}
                            onChange={(e) => setData('number', e.target.value)}
                        />
                        {errors.number && (
                            <p className="mt-1 text-xs text-danger">
                                {errors.number}
                            </p>
                        )}
                    </div>

                    <div>
                        <Select
                            aria-label="Tag"
                            placeholder="Pilih tag"
                            selectionMode="multiple"
                            value={data.tag_ids.map(String)}
                            onChange={(keys) =>
                                setData('tag_ids', (keys as Key[]).map(String))
                            }
                        >
                            <Label className="mb-1 block text-xs font-semibold text-foreground">
                                Tag
                            </Label>
                            <Select.Trigger>
                                <Select.Value />
                                <Select.Indicator />
                            </Select.Trigger>
                            <Select.Popover>
                                <ListBox selectionMode="multiple">
                                    {tagOptions.map((tag) => (
                                        <ListBox.Item
                                            key={String(tag.id)}
                                            id={String(tag.id)}
                                            textValue={tag.name}
                                        >
                                            {tag.name}
                                            <ListBox.ItemIndicator />
                                        </ListBox.Item>
                                    ))}
                                </ListBox>
                            </Select.Popover>
                        </Select>

                        <div className="mt-1.5 flex gap-1.5">
                            <Input
                                aria-label="Tag baru"
                                placeholder="Tambah tag baru"
                                value={newTag}
                                onChange={(e) => setNewTag(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                        void createTag();
                                    }
                                }}
                            />
                            <Button
                                variant="secondary"
                                onPress={() => void createTag()}
                                isDisabled={
                                    newTag.trim() === '' || isCreatingTag
                                }
                            >
                                Tambah
                            </Button>
                        </div>

                        {tagError && (
                            <p className="mt-1 text-xs text-danger">
                                {tagError}
                            </p>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-[2fr_1fr]">
                    <TextArea
                        aria-label="Alamat penagihan"
                        placeholder="Alamat penerima"
                        value={data.billing_address}
                        onChange={(e) =>
                            setData('billing_address', e.target.value)
                        }
                    />

                    <div className="flex flex-col justify-end gap-2">
                        <button
                            type="button"
                            onClick={toggleWithholding}
                            className="flex items-center gap-1.5 text-start text-xs font-medium text-accent hover:underline"
                        >
                            <Icon
                                className="size-4"
                                icon="gravity-ui:circle-plus"
                            />
                            Masukkan jumlah pemotongan
                        </button>

                        {showWithholding && (
                            <div className="grid grid-cols-1 gap-2 rounded-lg border border-border p-3 sm:grid-cols-3">
                                <Select
                                    aria-label="Tipe pemotongan"
                                    selectedKey={data.withholding?.type || null}
                                    onSelectionChange={(key) =>
                                        setData(
                                            'withholding',
                                            key === null
                                                ? null
                                                : {
                                                      type: String(key),
                                                      value:
                                                          data.withholding
                                                              ?.value ?? 0,
                                                      account_id:
                                                          data.withholding
                                                              ?.account_id ??
                                                          null,
                                                  },
                                        )
                                    }
                                >
                                    <Label className="text-xs">Tipe</Label>
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
                                                <ListBox.ItemIndicator />
                                            </ListBox.Item>
                                            <ListBox.Item
                                                id="nominal"
                                                textValue="Nominal"
                                            >
                                                Nominal
                                                <ListBox.ItemIndicator />
                                            </ListBox.Item>
                                        </ListBox>
                                    </Select.Popover>
                                </Select>

                                <NumberField
                                    aria-label="Nilai pemotongan"
                                    value={data.withholding?.value ?? 0}
                                    onChange={(value) =>
                                        setData(
                                            'withholding',
                                            value === null
                                                ? null
                                                : {
                                                      type:
                                                          data.withholding
                                                              ?.type ?? '',
                                                      value,
                                                      account_id:
                                                          data.withholding
                                                              ?.account_id ??
                                                          null,
                                                  },
                                        )
                                    }
                                >
                                    <Label className="text-xs">Nilai</Label>
                                    <NumberField.Input />
                                </NumberField>

                                <Select
                                    aria-label="Akun penampung pemotongan"
                                    selectedKey={
                                        data.withholding?.account_id
                                            ? String(
                                                  data.withholding.account_id,
                                              )
                                            : null
                                    }
                                    onSelectionChange={(key) =>
                                        setData(
                                            'withholding',
                                            key === null
                                                ? null
                                                : {
                                                      type:
                                                          data.withholding
                                                              ?.type ?? '',
                                                      value:
                                                          data.withholding
                                                              ?.value ?? 0,
                                                      account_id: String(key),
                                                  },
                                        )
                                    }
                                >
                                    <Label className="text-xs">
                                        Akun penampung
                                    </Label>
                                    <Select.Trigger>
                                        <Select.Value />
                                        <Select.Indicator />
                                    </Select.Trigger>
                                    <Select.Popover>
                                        <ListBox>
                                            {expenseAccounts.map((account) => (
                                                <ListBox.Item
                                                    key={String(account.id)}
                                                    id={String(account.id)}
                                                    textValue={`${account.code} ${account.name}`}
                                                >
                                                    {account.code} -{' '}
                                                    {account.name}
                                                    <ListBox.ItemIndicator />
                                                </ListBox.Item>
                                            ))}
                                        </ListBox>
                                    </Select.Popover>
                                </Select>
                            </div>
                        )}
                    </div>
                </div>

                <ExpenseItemsEditor
                    lines={data.lines}
                    accounts={expenseAccounts}
                    taxes={taxes}
                    isTaxInclusive={data.is_tax_inclusive}
                    onTaxInclusiveChange={(value) =>
                        setData('is_tax_inclusive', value)
                    }
                    onLineChange={setLine}
                    onAddLine={addLine}
                    onRemoveLine={removeLine}
                />

                {errors.lines && (
                    <p className="text-xs text-danger">{errors.lines}</p>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1.2fr_1fr]">
                    <div className="flex flex-col gap-4">
                        <TextArea
                            aria-label="Memo"
                            placeholder="Catatan internal, tidak dicetak di PDF"
                            value={data.memo}
                            onChange={(e) => setData('memo', e.target.value)}
                        />

                        <div>
                            <Label className="mb-1 block text-xs font-semibold text-foreground">
                                Lampiran
                            </Label>
                            <label className="border-border-strong flex cursor-pointer flex-col items-center gap-1 rounded-lg border border-dashed px-4 py-6 text-center text-xs text-muted hover:border-accent">
                                <Icon
                                    className="size-5"
                                    icon="gravity-ui:cloud-upload"
                                />
                                Tarik file ke sini, atau pilih file
                                <span className="text-[11px]">
                                    ukuran maksimal 10 MB/file
                                </span>
                                <input
                                    type="file"
                                    multiple
                                    className="hidden"
                                    onChange={(e) =>
                                        setData(
                                            'attachments',
                                            Array.from(e.target.files ?? []),
                                        )
                                    }
                                />
                            </label>
                        </div>
                    </div>

                    <div className="flex flex-col justify-end gap-2">
                        <div className="flex justify-between text-sm">
                            <span className="text-muted">Subtotal</span>
                            <span>{formatCurrency(totals.subtotal)}</span>
                        </div>
                        <div className="flex justify-between text-sm font-medium">
                            <span>Total</span>
                            <span>
                                {formatCurrency(
                                    totals.subtotal + totals.taxTotal,
                                )}
                            </span>
                        </div>
                        {totals.withholdingTotal > 0 && (
                            <div className="flex justify-between text-sm text-danger">
                                <span>Pemotongan</span>
                                <span>
                                    -{formatCurrency(totals.withholdingTotal)}
                                </span>
                            </div>
                        )}
                        <div className="flex justify-between border-t border-border pt-2 text-base font-semibold">
                            <span>Total akhir</span>
                            <span>{formatCurrency(totals.grandTotal)}</span>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    <Link href="/expenses">
                        <Button variant="danger" className="gap-1.5">
                            <Icon className="size-4" icon="gravity-ui:x" />
                            Batal
                        </Button>
                    </Link>

                    <Button
                        type="submit"
                        variant="primary"
                        className="gap-1.5"
                        isDisabled={processing}
                    >
                        Buat biaya baru
                    </Button>
                </div>
            </form>
        </CompanyLayout>
    );
}
