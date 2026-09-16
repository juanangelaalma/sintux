import {
    Button,
    Input,
    Label,
    ListBox,
    Select,
    TextField,
} from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { parseDate } from '@internationalized/date';
import { useEffect, useMemo, useState } from 'react';
import AutocompleteSelect from '@/components/ui/autocomplete-select';
import FormDatePicker from '@/components/ui/form-date-picker';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';
import InvoiceItemsEditor from './invoice-items-editor';
import { calculateInvoiceTotals } from './totals';
import type {
    CustomerOption,
    DiscountType,
    EmployeeOption,
    PaymentTermOption,
    SalesLineItemRow,
    SaleTaxOption,
    SaleVariantOption,
    SaleWarehouseOption,
} from './types';

type ActiveBranch = {
    id: number;
    name: string;
    code: string;
    is_headquarters: boolean;
};

type Props = {
    activeBranch?: ActiveBranch | null;
    warehouses: SaleWarehouseOption[];
    customers: CustomerOption[];
    employees: EmployeeOption[];
    productVariants: SaleVariantOption[];
    taxes: SaleTaxOption[];
    paymentTerms: PaymentTermOption[];
};

const todayStr = new Date().toISOString().split('T')[0];

const addDaysToDate = (baseDateStr: string, days: number): string => {
    if (!baseDateStr) {
        return '';
    }

    try {
        return parseDate(baseDateStr).add({ days }).toString();
    } catch {
        return baseDateStr;
    }
};

const getDaysFromTerm = (term: string): number | null => {
    if (!term) {
        return null;
    }

    const normalized = term.trim().toUpperCase();

    if (normalized === 'COD') {
        return 0;
    }

    const match = normalized.match(/NET\s*(\d+)/);

    if (match && match[1]) {
        return parseInt(match[1], 10);
    }

    return null;
};

const warehouseTypesFor = (transactionType: string): string[] =>
    transactionType === 'consignment' ? ['consignment'] : ['regular', 'retail'];

export default function SalesInvoicesCreate({
    activeBranch = null,
    warehouses,
    customers,
    employees,
    productVariants,
    taxes = [],
    paymentTerms = [],
}: Props) {
    const [customerEmail, setCustomerEmail] = useState(
        customers[0]?.email ?? '',
    );

    const defaultWarehouse = useMemo(
        () =>
            warehouses.find((w) => w.warehouse_type === 'regular') ??
            warehouses[0],
        [warehouses],
    );

    const { data, setData, post, processing, errors } = useForm<{
        customer_id: number | string;
        customer_email: string;
        transaction_type: string;
        warehouse_id: number | string;
        salesperson_id: number | string;
        payment_term: string;
        invoice_date: string;
        due_date: string;
        is_tax_inclusive: boolean;
        invoice_discount_type: DiscountType | '';
        invoice_discount_value: number;
        items: SalesLineItemRow[];
    }>({
        customer_id: customers[0]?.id ?? '',
        customer_email: customers[0]?.email ?? '',
        transaction_type: 'regular',
        warehouse_id: defaultWarehouse?.id ?? '',
        salesperson_id: employees[0]?.id ?? '',
        payment_term: paymentTerms[0]?.id ?? 'NET 30',
        invoice_date: todayStr,
        due_date: todayStr,
        is_tax_inclusive: false,
        invoice_discount_type: '',
        invoice_discount_value: 0,
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                qty: 1,
                unit_price: Number(productVariants[0]?.selling_price ?? 0),
                discount_type: '',
                discount_value: 0,
                tax_id: null,
            },
        ],
    });

    const visibleWarehouses = useMemo(
        () =>
            warehouses.filter((w) =>
                warehouseTypesFor(data.transaction_type).includes(
                    w.warehouse_type,
                ),
            ),
        [warehouses, data.transaction_type],
    );

    // Gudang selalu valid terhadap tipe transaksi (default regular).
    useEffect(() => {
        const stillValid = visibleWarehouses.some(
            (w) => w.id === Number(data.warehouse_id),
        );

        if (!stillValid) {
            setData(
                'warehouse_id',
                visibleWarehouses[0]?.id ?? defaultWarehouse?.id ?? '',
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [data.transaction_type, warehouses]);

    const handleCustomerChange = (id: number) => {
        const selected = customers.find((c) => c.id === id);

        if (selected?.email) {
            setCustomerEmail(selected.email);
        }

        setData((prev) => ({
            ...prev,
            customer_id: id,
            customer_email: selected?.email ?? prev.customer_email,
        }));
    };

    const handleInvoiceDateChange = (nextDate: string) => {
        // Tgl. transaksi tidak boleh melebihi hari ini (YMD bisa
        // dibandingkan sebagai string). Kalender sudah disable tanggal
        // future; ini pengaman untuk input ketik manual.
        const clampedDate =
            nextDate && nextDate > todayStr ? todayStr : nextDate;

        setData((prev) => {
            const days = getDaysFromTerm(prev.payment_term);
            const nextDueDate =
                days !== null
                    ? addDaysToDate(clampedDate, days)
                    : prev.due_date;

            return {
                ...prev,
                invoice_date: clampedDate,
                due_date: nextDueDate,
            };
        });
    };

    const handlePaymentTermChange = (newTerm: string) => {
        const days = getDaysFromTerm(newTerm);

        if (days !== null && data.invoice_date) {
            const nextDueDate = addDaysToDate(data.invoice_date, days);
            setData((prev) => ({
                ...prev,
                payment_term: newTerm,
                due_date: nextDueDate,
            }));
        } else {
            setData('payment_term', newTerm);
        }
    };

    const handleDueDateChange = (nextDueDate: string) => {
        setData((prev) => {
            const days = getDaysFromTerm(prev.payment_term);
            const expectedDue =
                days !== null && prev.invoice_date
                    ? addDaysToDate(prev.invoice_date, days)
                    : null;
            const updatedTerm =
                expectedDue && expectedDue !== nextDueDate
                    ? 'Custom'
                    : prev.payment_term;

            return {
                ...prev,
                due_date: nextDueDate,
                payment_term: updatedTerm,
            };
        });
    };

    const addItem = () => {
        setData((prev) => ({
            ...prev,
            items: [
                ...prev.items,
                {
                    product_variant_id: productVariants[0]?.id ?? 0,
                    qty: 1,
                    unit_price: Number(productVariants[0]?.selling_price ?? 0),
                    discount_type: '',
                    discount_value: 0,
                    tax_id: null,
                },
            ],
        }));
    };

    const removeItem = (index: number) => {
        setData((prev) => {
            if (prev.items.length === 1) {
                return prev;
            }

            const next = [...prev.items];
            next.splice(index, 1);

            return { ...prev, items: next };
        });
    };

    const updateItem = (
        index: number,
        field: keyof SalesLineItemRow,
        value: string | number | null,
    ) => {
        setData((prev) => {
            const next = [...prev.items];
            next[index] = { ...next[index], [field]: value };

            return { ...prev, items: next };
        });
    };

    const totals = calculateInvoiceTotals(
        data.items,
        taxes,
        data.invoice_discount_type,
        data.invoice_discount_value,
        data.is_tax_inclusive,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/sales/invoices');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Faktur Penjualan" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Penjualan
                            {activeBranch ? ` · ${activeBranch.name}` : ''}
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Faktur Penjualan
                        </h1>
                    </div>
                    <div className="text-right">
                        <p className="text-xs font-medium text-muted">
                            Total Tagihan
                        </p>
                        <p className="mt-0.5 text-2xl font-black text-foreground">
                            {formatCurrency(totals.total)}
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                        <AutocompleteSelect
                            label="Pelanggan"
                            placeholder="Cari pelanggan..."
                            searchPlaceholder="Cari nama pelanggan..."
                            items={customers.map((c) => ({
                                id: c.id,
                                name: c.name,
                            }))}
                            value={data.customer_id}
                            onChange={handleCustomerChange}
                            error={errors.customer_id}
                            isRequired
                        />

                        <TextField name="customer_email">
                            <Label className="block text-xs font-semibold text-foreground">
                                Email
                            </Label>
                            <Input
                                className="mt-1"
                                placeholder="Masukkan email"
                                value={customerEmail}
                                onChange={(e) => {
                                    setCustomerEmail(e.target.value);
                                    setData('customer_email', e.target.value);
                                }}
                            />
                        </TextField>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Tipe Transaksi *
                            </Label>
                            <Select
                                fullWidth
                                isRequired
                                value={data.transaction_type}
                                onChange={(val) =>
                                    setData('transaction_type', String(val))
                                }
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        <ListBox.Item
                                            id="regular"
                                            textValue="Reguler"
                                        >
                                            Reguler
                                        </ListBox.Item>
                                        <ListBox.Item
                                            id="consignment"
                                            textValue="Konsinyasi"
                                        >
                                            Konsinyasi
                                        </ListBox.Item>
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                        </div>

                        <FormDatePicker
                            label="Tgl. Transaksi"
                            value={data.invoice_date}
                            onChange={handleInvoiceDateChange}
                            maxValue={todayStr}
                            error={errors.invoice_date}
                        />

                        <FormDatePicker
                            label="Tgl. Jatuh Tempo"
                            value={data.due_date}
                            onChange={handleDueDateChange}
                            minValue={data.invoice_date || undefined}
                            isRequired={false}
                            error={errors.due_date}
                        />

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Syarat Pembayaran
                            </Label>
                            <Select
                                fullWidth
                                placeholder="Pilih termin"
                                value={data.payment_term}
                                onChange={(val) =>
                                    handlePaymentTermChange(String(val))
                                }
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        {paymentTerms.map((term) => (
                                            <ListBox.Item
                                                key={term.id}
                                                id={term.id}
                                                textValue={term.name}
                                            >
                                                {term.name}
                                            </ListBox.Item>
                                        ))}
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Gudang *
                            </Label>
                            <Select
                                fullWidth
                                isRequired
                                isInvalid={Boolean(errors.warehouse_id)}
                                placeholder="Pilih gudang"
                                value={
                                    data.warehouse_id
                                        ? String(data.warehouse_id)
                                        : ''
                                }
                                onChange={(val) =>
                                    setData('warehouse_id', Number(val))
                                }
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        {visibleWarehouses.map((w) => (
                                            <ListBox.Item
                                                key={w.id}
                                                id={String(w.id)}
                                                textValue={`${w.code} — ${w.name}`}
                                            >
                                                {w.code} — {w.name}
                                            </ListBox.Item>
                                        ))}
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                            {errors.warehouse_id && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.warehouse_id}
                                </p>
                            )}
                        </div>

                        <AutocompleteSelect
                            label="Sales / Marketing"
                            placeholder="Cari karyawan..."
                            searchPlaceholder="Cari nama karyawan..."
                            items={employees.map((emp) => ({
                                id: emp.id,
                                name: emp.name,
                            }))}
                            value={data.salesperson_id}
                            onChange={(id) => setData('salesperson_id', id)}
                            error={errors.salesperson_id}
                            isRequired
                        />
                    </div>

                    <InvoiceItemsEditor
                        items={data.items}
                        productVariants={productVariants}
                        taxes={taxes}
                        isTaxInclusive={data.is_tax_inclusive}
                        onTaxInclusiveChange={(inclusive) =>
                            setData('is_tax_inclusive', inclusive)
                        }
                        invoiceDiscountType={data.invoice_discount_type}
                        invoiceDiscountValue={data.invoice_discount_value}
                        onInvoiceDiscountTypeChange={(type) =>
                            setData('invoice_discount_type', type)
                        }
                        onInvoiceDiscountValueChange={(value) =>
                            setData('invoice_discount_value', value)
                        }
                        onAddItem={addItem}
                        onRemoveItem={removeItem}
                        onUpdateItem={updateItem}
                        errors={errors}
                    />
                    {errors.invoice_discount_value && (
                        <p className="-mt-3 text-xs text-danger">
                            {errors.invoice_discount_value}
                        </p>
                    )}

                    <div className="flex justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/sales/invoices">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing}
                        >
                            Simpan Faktur Penjualan
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
