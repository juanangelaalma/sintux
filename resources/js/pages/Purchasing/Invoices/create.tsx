import {
    Button,
    Input,
    Label,
    ListBox,
    Select,
    TextField,
} from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import LineItemsEditor from '@/components/purchasing/line-items-editor';
import type {
    LineItemRow,
    ProductVariant,
} from '@/components/purchasing/line-items-editor';
import SupplierFields from '@/components/purchasing/supplier-fields';
import type { SupplierOption } from '@/components/purchasing/supplier-fields';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';

type Branch = { id: number; name: string; code: string };
type Warehouse = { id: number; name: string };

export type POItem = {
    id: number;
    product_variant_id: number;
    product_name: string;
    sku: string;
    qty: number;
    unit_price: number;
    line_total: number;
};

export type PurchaseOrderOption = {
    id: number;
    number: string;
    supplier_id: number;
    order_date: string;
    expected_date?: string;
    note?: string;
    subtotal?: number;
    total?: number;
    items: POItem[];
};

type Props = {
    branches: Branch[];
    suppliers: SupplierOption[];
    productVariants: ProductVariant[];
    purchaseOrders?: PurchaseOrderOption[];
    warehouses?: Warehouse[];
};

export default function PurchaseInvoicesCreate({
    branches,
    suppliers,
    productVariants,
    purchaseOrders = [],
}: Props) {
    const [supplierEmail, setSupplierEmail] = useState('');
    const [supplierAddress, setSupplierAddress] = useState('');
    const [poSearch, setPoSearch] = useState('');

    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        purchase_order_id?: number | string;
        invoice_date: string;
        due_date: string;
        note: string;
        items: LineItemRow[];
    }>({
        branch_id: branches[0]?.id ?? '',
        supplier_id: suppliers[0]?.id ?? '',
        purchase_order_id: '',
        invoice_date: new Date().toISOString().split('T')[0],
        due_date: '',
        note: '',
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                unit_price: 0,
            },
        ],
    });

    const handleSelectPO = (poId: number | string) => {
        const po = purchaseOrders.find((p) => p.id === Number(poId));

        if (!po) {
            return;
        }

        const supplier = suppliers.find((s) => s.id === po.supplier_id);

        if (supplier) {
            if (supplier.email) {
                setSupplierEmail(supplier.email);
            }

            if (supplier.billing_address) {
                setSupplierAddress(supplier.billing_address);
            }
        }

        const poItems: LineItemRow[] = po.items.map((item) => ({
            product_variant_id: item.product_variant_id,
            purchase_order_item_id: item.id,
            description: `${item.product_name} (${item.sku})`,
            qty: item.qty,
            unit_price: item.unit_price,
        }));

        setData({
            ...data,
            purchase_order_id: po.id,
            supplier_id: po.supplier_id,
            note: po.note || data.note,
            items: poItems.length > 0 ? poItems : data.items,
        });
    };

    const handleSearchPONumber = (num: string) => {
        setPoSearch(num);
        const matched = purchaseOrders.find(
            (p) => p.number.toLowerCase() === num.trim().toLowerCase(),
        );

        if (matched) {
            handleSelectPO(matched.id);
        }
    };

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                unit_price: 0,
            },
        ]);
    };

    const removeItem = (index: number) => {
        if (data.items.length === 1) {
            return;
        }

        const next = [...data.items];
        next.splice(index, 1);
        setData('items', next);
    };

    const updateItem = (
        index: number,
        field: keyof LineItemRow,
        value: string | number | null,
    ) => {
        const next = [...data.items];
        next[index] = { ...next[index], [field]: value };
        setData('items', next);
    };

    const subtotal = data.items.reduce(
        (sum, item) =>
            sum + Number(item.qty || 0) * Number(item.unit_price || 0),
        0,
    );

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/invoices');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Faktur Pembelian" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Faktur Pembelian (Invoice)
                        </h1>
                    </div>
                    <div className="text-right">
                        <p className="text-xs font-medium text-muted">
                            Total Tagihan
                        </p>
                        <p className="mt-0.5 text-2xl font-black text-foreground">
                            {formatCurrency(subtotal)}
                        </p>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    {/* Referensi PO (Pilih atau copas nomor PO) */}
                    <div className="grid grid-cols-1 gap-4 rounded-lg border border-border bg-surface-secondary p-4 md:grid-cols-2">
                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Ambil Data dari Purchase Order (PO)
                            </Label>
                            <Select
                                fullWidth
                                placeholder="Pilih PO terdaftar..."
                                value={
                                    data.purchase_order_id
                                        ? String(data.purchase_order_id)
                                        : ''
                                }
                                onChange={(val) => handleSelectPO(String(val))}
                            >
                                <Select.Trigger className="mt-1">
                                    <Select.Value />
                                    <Select.Indicator />
                                </Select.Trigger>
                                <Select.Popover>
                                    <ListBox>
                                        {purchaseOrders.map((po) => (
                                            <ListBox.Item
                                                key={po.id}
                                                id={String(po.id)}
                                                textValue={`PO #${po.number}`}
                                            >
                                                PO #{po.number} (Total:{' '}
                                                {formatCurrency(po.total)})
                                            </ListBox.Item>
                                        ))}
                                    </ListBox>
                                </Select.Popover>
                            </Select>
                        </div>

                        <div>
                            <TextField name="po_number_copy">
                                <Label className="block text-xs font-semibold text-foreground">
                                    atau Ketik / Copas Nomor PO (e.g.
                                    PO-AIIJKT-0001)
                                </Label>
                                <div className="mt-1 flex items-center gap-2">
                                    <Input
                                        placeholder="Copas nomor PO di sini..."
                                        value={poSearch}
                                        onChange={(e) =>
                                            handleSearchPONumber(e.target.value)
                                        }
                                    />
                                    <Button
                                        isIconOnly
                                        variant="secondary"
                                        aria-label="Cari PO"
                                        onPress={() =>
                                            handleSearchPONumber(poSearch)
                                        }
                                    >
                                        <Search className="size-4 text-muted" />
                                    </Button>
                                </div>
                            </TextField>
                        </div>
                    </div>

                    <SupplierFields
                        supplierId={data.supplier_id}
                        suppliers={suppliers}
                        onSupplierChange={(id) => setData('supplier_id', id)}
                        email={supplierEmail}
                        address={supplierAddress}
                        onEmailChange={setSupplierEmail}
                        onAddressChange={setSupplierAddress}
                        error={errors.supplier_id}
                    />

                    <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal faktur *
                            </label>
                            <input
                                type="date"
                                value={data.invoice_date}
                                onChange={(e) =>
                                    setData('invoice_date', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal jatuh tempo
                            </label>
                            <input
                                type="date"
                                value={data.due_date}
                                onChange={(e) =>
                                    setData('due_date', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                        </div>
                    </div>

                    <LineItemsEditor
                        items={data.items}
                        productVariants={productVariants}
                        onAddItem={addItem}
                        onRemoveItem={removeItem}
                        onUpdateItem={updateItem}
                    />

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
                            Simpan Faktur Pembelian
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
