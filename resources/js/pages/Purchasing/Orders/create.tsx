import { Button, TextArea } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { parseDate } from '@internationalized/date';
import { Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import LineItemsEditor from '@/components/purchasing/line-items-editor';
import type {
    LineItemRow,
    ProductVariant,
    TaxOption,
} from '@/components/purchasing/line-items-editor';
import { SupplierComboBox } from '@/components/ui/app-combobox/suppliers/supplier-combobox';
import { TagComboBox } from '@/components/ui/app-combobox/tags/tag-combobox';
import type { TagItem } from '@/components/ui/app-combobox/tags/tag-combobox';
import FormDatePicker from '@/components/ui/form-date-picker';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';

type Branch = { id: number; name: string; code: string };
type Warehouse = { id: number; name: string };
type PurchaseRequestItem = {
    product_variant_id: number;
    qty_requested: number;
    estimated_unit_price: number;
    description?: string;
};
type PurchaseRequestOption = {
    id: number;
    number: string;
    items: PurchaseRequestItem[];
};
type PaymentTermOption = { id: string; name: string };

type SupplierOption = {
    id: number;
    name: string;
    code?: string;
    email?: string;
    address?: string;
};

type Props = {
    activeBranch?: Branch;
    branches: Branch[];
    warehouses: Warehouse[];
    suppliers: SupplierOption[];
    purchaseRequests: PurchaseRequestOption[];
    paymentTerms: PaymentTermOption[];
    taxes: TaxOption[];
    productVariants: ProductVariant[];
};

export default function PurchaseOrdersCreate({
    activeBranch,
    branches,
    warehouses,
    suppliers,
    purchaseRequests,
    paymentTerms,
    taxes,
    productVariants,
}: Props) {
    const currentBranch = activeBranch ?? branches[0];
    const [supplierEmail, setSupplierEmail] = useState(
        suppliers[0]?.email || '',
    );
    const [supplierAddress, setSupplierAddress] = useState(
        suppliers[0]?.address || '',
    );
    const [supplierRef, setSupplierRef] = useState('');
    const [selectedTagIds, setSelectedTagIds] = useState<(string | number)[]>(
        [],
    );
    const [tagsList, setTagsList] = useState<TagItem[]>([
        { id: '1', name: 'Prioritas' },
        { id: '2', name: 'Urgent' },
        { id: '3', name: 'Reguler' },
        { id: '4', name: 'Impor' },
        { id: '5', name: 'Lokal' },
    ]);

    const handleCreateTag = (name: string) => {
        const newTag: TagItem = {
            id: String(Date.now()),
            name,
        };
        setTagsList((prev) => [...prev, newTag]);
        setSelectedTagIds((prev) => [...prev, newTag.id]);
    };

    const todayStr = useMemo(() => new Date().toISOString().split('T')[0], []);
    const formattedDate = useMemo(() => {
        const d = new Date();
        const yyyy = d.getFullYear();
        const mm = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');

        return `${yyyy}/${mm}/${dd}`;
    }, []);

    const previewNumber = `PO/${currentBranch?.code ?? 'HQ'}/${formattedDate}/001`;

    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        warehouse_id: number | string;
        source_request_id: number | string;
        payment_term: string;
        order_date: string;
        due_date: string;
        is_tax_inclusive: boolean;
        note: string;
        items: (LineItemRow & { qty_ordered?: number })[];
    }>({
        branch_id: currentBranch?.id ?? '',
        supplier_id: suppliers[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        source_request_id: '',
        payment_term: paymentTerms[0]?.id ?? 'Net 30',
        order_date: todayStr,
        due_date: todayStr,
        is_tax_inclusive: false,
        note: '',
        items: [
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                qty_ordered: 1,
                unit_price: 0,
                tax_id: null,
            },
        ],
    });

    const addDaysToDate = (baseDateStr: string, days: number): string => {
        if (!baseDateStr) {
            return '';
        }

        try {
            const date = parseDate(baseDateStr);
            const nextDate = date.add({ days });

            return nextDate.toString();
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

    const handleOrderDateChange = (newOrderDate: string) => {
        setData((prev) => {
            const days = getDaysFromTerm(prev.payment_term);
            const nextDueDate =
                days !== null
                    ? addDaysToDate(newOrderDate, days)
                    : prev.due_date;

            return {
                ...prev,
                order_date: newOrderDate,
                due_date: nextDueDate,
            };
        });
    };

    const handlePaymentTermChange = (newTerm: string) => {
        const days = getDaysFromTerm(newTerm);

        if (days !== null && data.order_date) {
            const nextDueDate = addDaysToDate(data.order_date, days);
            setData((prev) => ({
                ...prev,
                payment_term: newTerm,
                due_date: nextDueDate,
            }));
        } else {
            setData('payment_term', newTerm);
        }
    };

    const handleDueDateChange = (newDueDate: string) => {
        setData((prev) => {
            const days = getDaysFromTerm(prev.payment_term);
            const expectedDue =
                days !== null && prev.order_date
                    ? addDaysToDate(prev.order_date, days)
                    : null;
            const updatedTerm =
                expectedDue && expectedDue !== newDueDate
                    ? 'Custom'
                    : prev.payment_term;

            return {
                ...prev,
                due_date: newDueDate,
                payment_term: updatedTerm,
            };
        });
    };

    const handleSourceRequestChange = (reqIdStr: string) => {
        const reqId = reqIdStr ? Number(reqIdStr) : '';
        setData('source_request_id', reqId);

        if (!reqId) {
            return;
        }

        const selectedPR = purchaseRequests.find((pr) => pr.id === reqId);

        if (selectedPR && selectedPR.items && selectedPR.items.length > 0) {
            const mappedItems = selectedPR.items.map((pi) => ({
                product_variant_id: pi.product_variant_id,
                description: pi.description || '',
                qty: Number(pi.qty_requested || 1),
                qty_ordered: Number(pi.qty_requested || 1),
                unit_price: Number(pi.estimated_unit_price || 0),
                tax_id: null,
            }));
            setData('items', mappedItems);
        }
    };

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                product_variant_id: productVariants[0]?.id ?? 0,
                description: '',
                qty: 1,
                qty_ordered: 1,
                unit_price: 0,
                tax_id: null,
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
        const updatedItem = { ...next[index], [field]: value };

        if (field === 'qty') {
            updatedItem.qty_ordered = Number(value);
        }

        next[index] = updatedItem;
        setData('items', next);
    };

    const summary = useMemo(() => {
        let subtotal = 0;
        let ppn = 0;

        data.items.forEach((item) => {
            const qty = Number(item.qty || 0);
            const price = Number(item.unit_price || 0);
            const lineGross = qty * price;

            const selectedTax = taxes.find((t) => t.id === item.tax_id);
            const rate = selectedTax ? Number(selectedTax.rate) : 0;

            if (data.is_tax_inclusive && rate > 0) {
                const lineSubtotal = lineGross / (1 + rate / 100);
                const lineTax = lineGross - lineSubtotal;
                subtotal += lineSubtotal;
                ppn += lineTax;
            } else {
                const lineTax = lineGross * (rate / 100);
                subtotal += lineGross;
                ppn += lineTax;
            }
        });

        const total = subtotal + ppn;

        return { subtotal, ppn, total };
    }, [data.items, data.is_tax_inclusive, taxes]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const preparedItems = data.items.map((item) => ({
            ...item,
            qty_ordered: Number(item.qty),
            unit_price: Number(item.unit_price || 0),
        }));

        setData('items', preparedItems);
        post('/purchasing/orders');
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLFormElement>) => {
        if (e.key === 'Enter') {
            const target = e.target as HTMLElement;

            if (
                target.tagName === 'INPUT' ||
                target.tagName === 'SELECT' ||
                target.tagName === 'TEXTAREA'
            ) {
                e.preventDefault();
            }
        }
    };

    return (
        <CompanyLayout>
            <Head title="Buat Pesanan Pembelian" />
            <div className="w-full space-y-6">
                <form
                    onSubmit={handleSubmit}
                    onKeyDown={handleKeyDown}
                    className="space-y-6"
                >
                    {/* TOP SECTION: Supplier, Email & Total Banner */}
                    <div className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs">
                        <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                            {/* Supplier */}
                            <div className="lg:col-span-4">
                                <SupplierComboBox
                                    suppliers={suppliers}
                                    value={data.supplier_id}
                                    onChange={(selectedId) => {
                                        setData(
                                            'supplier_id',
                                            selectedId ?? '',
                                        );
                                        const s = suppliers.find(
                                            (sup) => sup.id === selectedId,
                                        );

                                        if (s) {
                                            setSupplierEmail(s.email || '');
                                            setSupplierAddress(s.address || '');
                                        }
                                    }}
                                    error={errors.supplier_id}
                                />
                            </div>

                            {/* Email */}
                            <div className="lg:col-span-5">
                                <label className="mb-1 block text-xs font-semibold text-foreground">
                                    Email
                                </label>
                                <input
                                    type="email"
                                    placeholder="e.g. john@example.com"
                                    value={supplierEmail}
                                    onChange={(e) =>
                                        setSupplierEmail(e.target.value)
                                    }
                                    className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                />
                            </div>

                            {/* Total Header Right */}
                            <div className="pt-2 text-right lg:col-span-3 lg:pt-0">
                                <p className="text-2xl font-black text-foreground">
                                    Total {formatCurrency(summary.total)}
                                </p>
                            </div>
                        </div>

                        <div className="border-t border-dashed border-border" />

                        {/* DETAIL GRID: Alamat Penagihan, Tanggal/Termin, Nomor Transaksi/Ref/Gudang, Tag */}
                        <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                            {/* Kolom 1: Alamat Penagihan */}
                            <div className="lg:col-span-3">
                                <label className="mb-1 block text-xs font-semibold text-foreground">
                                    Alamat penagihan
                                </label>
                                <textarea
                                    rows={8}
                                    placeholder="e.g. Jalan Indonesia Blok C No. 22"
                                    value={supplierAddress}
                                    onChange={(e) =>
                                        setSupplierAddress(e.target.value)
                                    }
                                    className="h-[180px] w-full resize-none rounded-lg border border-border bg-surface p-3 text-sm text-foreground focus:border-accent focus:ring-accent"
                                />
                            </div>

                            {/* Kolom 2: Tgl. transaksi, Tgl. jatuh tempo, Syarat pembayaran */}
                            <div className="space-y-3 lg:col-span-3">
                                <div>
                                    <FormDatePicker
                                        label="Tgl. Transaksi"
                                        value={data.order_date}
                                        onChange={handleOrderDateChange}
                                    />
                                </div>
                                <div>
                                    <FormDatePicker
                                        label="Tgl. Jatuh Tempo"
                                        value={data.due_date}
                                        onChange={handleDueDateChange}
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">
                                        Syarat pembayaran
                                    </label>
                                    <select
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                        value={data.payment_term}
                                        onChange={(e) =>
                                            handlePaymentTermChange(
                                                e.target.value,
                                            )
                                        }
                                    >
                                        {paymentTerms.map((pt) => (
                                            <option key={pt.id} value={pt.id}>
                                                {pt.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Kolom 3: No Transaksi, Nomor referensi supplier, Gudang */}
                            <div className="space-y-3 lg:col-span-3">
                                <div>
                                    <div className="mb-1 flex items-center gap-1">
                                        <label className="block text-xs font-semibold text-foreground">
                                            No Transaksi
                                        </label>
                                        <Settings className="size-3.5 cursor-pointer text-muted hover:text-foreground" />
                                    </div>
                                    <input
                                        type="text"
                                        readOnly
                                        value={previewNumber}
                                        placeholder="[Auto]"
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm font-medium text-foreground focus:border-accent focus:ring-accent"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">
                                        Nomor referensi supplier
                                    </label>
                                    <input
                                        type="text"
                                        value={supplierRef}
                                        onChange={(e) =>
                                            setSupplierRef(e.target.value)
                                        }
                                        placeholder=""
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">
                                        Gudang
                                    </label>
                                    <select
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                        value={String(data.warehouse_id)}
                                        onChange={(e) =>
                                            setData(
                                                'warehouse_id',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">Pilih gudang</option>
                                        {warehouses.map((wh) => (
                                            <option key={wh.id} value={wh.id}>
                                                {wh.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Kolom 4: Tag & Referensi PR */}
                            <div className="space-y-3 lg:col-span-3">
                                <div>
                                    <TagComboBox
                                        tags={tagsList}
                                        selectedTagIds={selectedTagIds}
                                        onSelectedTagsChange={setSelectedTagIds}
                                        onCreateTag={handleCreateTag}
                                        placeholder="Pilih atau cari tag"
                                    />
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">
                                        Nomor Permintaan (PR)
                                    </label>
                                    <select
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                        value={String(data.source_request_id)}
                                        onChange={(e) =>
                                            handleSourceRequestChange(
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="">
                                            -- Pilih PR (Opsional) --
                                        </option>
                                        {purchaseRequests.map((pr) => (
                                            <option key={pr.id} value={pr.id}>
                                                {pr.number}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Line Items Table Section */}
                    <LineItemsEditor
                        items={data.items}
                        productVariants={productVariants}
                        taxes={taxes}
                        isTaxInclusive={data.is_tax_inclusive}
                        onTaxInclusiveChange={(inclusive) =>
                            setData('is_tax_inclusive', inclusive)
                        }
                        onAddItem={addItem}
                        onRemoveItem={removeItem}
                        onUpdateItem={updateItem}
                    />

                    {/* Asymmetric Footer: Memo 5/12 + Summary 7/12 */}
                    <div className="grid grid-cols-1 gap-6 rounded-xl border border-border bg-surface p-6 shadow-xs lg:grid-cols-12">
                        <div className="space-y-2 lg:col-span-7">
                            <label className="block text-xs font-semibold text-foreground">
                                Memo / Catatan Internal
                            </label>
                            <TextArea
                                fullWidth
                                rows={4}
                                placeholder="Masukkan pesan, catatan khusus supplier, atau instruksi pengiriman..."
                                value={data.note}
                                onChange={(e) =>
                                    setData('note', e.target.value)
                                }
                            />
                        </div>

                        <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-surface-secondary/40 p-4 lg:col-span-5">
                            <h3 className="mb-3 border-b border-border/40 pb-2 text-xs font-bold tracking-wider text-muted uppercase">
                                Ringkasan Pembayaran
                            </h3>
                            <div className="space-y-2.5 text-sm">
                                <div className="flex justify-between text-muted">
                                    <span>Subtotal</span>
                                    <span className="font-medium text-foreground">
                                        {formatCurrency(summary.subtotal)}
                                    </span>
                                </div>
                                <div className="flex justify-between text-muted">
                                    <span>Pajak PPN</span>
                                    <span className="font-medium text-foreground">
                                        {formatCurrency(summary.ppn)}
                                    </span>
                                </div>
                                <div className="flex justify-between border-t border-border pt-3 text-base font-bold text-foreground">
                                    <span>Total Akhir</span>
                                    <span className="text-lg text-accent">
                                        {formatCurrency(summary.total)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Bottom Actions */}
                    <div className="flex items-center justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/purchasing/orders">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing}
                        >
                            Simpan Pesanan Pembelian
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
