import { Button, TextArea } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { parseDate } from '@internationalized/date';
import { AlertTriangle, ArrowRight, Settings } from 'lucide-react';
import { useMemo, useState } from 'react';
import { AddBranchBar } from '@/components/purchasing/branch-allocation/AddBranchBar';
import { BranchAllocationCard } from '@/components/purchasing/branch-allocation/BranchAllocationCard';
import { useBranchGroups } from '@/components/purchasing/branch-allocation/useBranchGroups';
import type { TaxOption } from '@/components/purchasing/line-items-editor';
import { SupplierComboBox } from '@/components/ui/app-combobox/suppliers/supplier-combobox';
import { TagComboBox } from '@/components/ui/app-combobox/tags/tag-combobox';
import type { TagItem } from '@/components/ui/app-combobox/tags/tag-combobox';
import FormDatePicker from '@/components/ui/form-date-picker';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';
import { getLineTotals } from '@/lib/purchasing/calc';

type Branch = {
    id: number;
    name: string;
    code: string;
    is_headquarters: boolean;
};
type Warehouse = { id: number; name: string; code: string };
type PaymentTermOption = { id: string; name: string };

type SupplierOption = {
    id: number;
    name: string;
    code?: string;
    email?: string;
    address?: string;
    is_ho_only?: boolean;
};

type ProductVariant = {
    id: number;
    branch_id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
};

type Props = {
    hqBranch?: Branch;
    activeBranch?: Branch;
    branches: Branch[];
    warehouses: Warehouse[];
    warehousesByBranch: Record<string, Warehouse[]>;
    hqWarehouse?: Warehouse | null;
    suppliers: SupplierOption[];
    paymentTerms: PaymentTermOption[];
    taxes: TaxOption[];
    productVariants: ProductVariant[];
    tags: TagItem[];
};

export default function PurchaseOrdersCreate({
    hqBranch,
    activeBranch,
    branches,
    warehouses,
    warehousesByBranch,
    hqWarehouse,
    suppliers,
    paymentTerms,
    taxes,
    productVariants,
    tags,
}: Props) {
    const currentBranch = hqBranch ?? activeBranch ?? branches.find((b) => b.is_headquarters) ?? branches[0];
    const initialSupplier = suppliers[0];

    const [createdTags, setCreatedTags] = useState<TagItem[]>([]);
    const allTags = useMemo(() => [...tags, ...createdTags], [tags, createdTags]);
    const [supplierInputValue, setSupplierInputValue] = useState(initialSupplier?.name || '');

    const todayStr = useMemo(() => new Date().toISOString().split('T')[0], []);
    const formattedDate = useMemo(() => {
        const d = new Date();

        return `${d.getFullYear()}/${String(d.getMonth() + 1).padStart(2, '0')}/${String(d.getDate()).padStart(2, '0')}`;
    }, []);
    const previewNumber = `PO/${currentBranch?.code ?? 'HQ'}/${formattedDate}/001`;

    const {
        groups,
        sortedGroups,
        expanded,
        availableBranches,
        flatItems,
        totalAllocations,
        toggleGroup,
        addBranchGroup,
        removeBranchGroup,
        changeGroupDate,
        addItemToGroup,
        removeItemFromGroup,
        updateItemInGroup,
    } = useBranchGroups({
        branches,
        warehousesByBranch,
        productVariants,
        initialDate: todayStr,
        initialBranchId: branches.find((b) => !b.is_headquarters)?.id ?? branches[0]?.id,
    });

    const { data, setData, post, processing, errors, transform } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        supplier_email: string;
        supplier_reference: string;
        billing_address: string;
        warehouse_id: number | string;
        payment_term: string;
        order_date: string;
        due_date: string;
        is_tax_inclusive: boolean;
        note: string;
        tag_ids: number[];
        items: never[];
    }>({
        branch_id: currentBranch?.id ?? '',
        supplier_id: initialSupplier?.id ?? '',
        supplier_email: initialSupplier?.email || '',
        supplier_reference: '',
        billing_address: initialSupplier?.address || '',
        warehouse_id: hqWarehouse?.id ?? warehouses[0]?.id ?? '',
        payment_term: paymentTerms[0]?.id ?? 'NET 30',
        order_date: todayStr,
        due_date: todayStr,
        is_tax_inclusive: false,
        note: '',
        tag_ids: [],
        items: [] as never[],
    });

    const handleCreateTag = async (name: string) => {
        if (!name.trim()) {
            return;
        }

        const csrfToken = (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';

        try {
            const res = await fetch('/purchasing/tags', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify({ name }),
            });
            const created = await res.json();

            if (res.ok && created.id) {
                setCreatedTags((prev) => (prev.some((t) => t.id === created.id) ? prev : [...prev, { id: created.id, name: created.name }]));
                setData((prev) => (prev.tag_ids.includes(created.id) ? prev : { ...prev, tag_ids: [...prev.tag_ids, created.id] }));
            }
        } catch {
            // noop
        }
    };

    const selectedSupplier = useMemo(() => suppliers.find((s) => s.id === data.supplier_id), [suppliers, data.supplier_id]);
    const isHoOnlySupplier = (selectedSupplier?.is_ho_only ?? false) && !(currentBranch?.is_headquarters ?? false);

    const addDaysToDate = (baseDateStr: string, days: number): string => {
        if (!baseDateStr) {
            return '';
        }

        try {
            const date = parseDate(baseDateStr);

            return date.add({ days }).toString();
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
            const nextDueDate = days !== null ? addDaysToDate(newOrderDate, days) : prev.due_date;

            return { ...prev, order_date: newOrderDate, due_date: nextDueDate };
        });
    };

    const handlePaymentTermChange = (newTerm: string) => {
        const days = getDaysFromTerm(newTerm);

        if (days !== null && data.order_date) {
            const nextDueDate = addDaysToDate(data.order_date, days);
            setData((prev) => ({ ...prev, payment_term: newTerm, due_date: nextDueDate }));
        } else {
            setData('payment_term', newTerm);
        }
    };

    const handleDueDateChange = (newDueDate: string) => {
        setData((prev) => {
            const days = getDaysFromTerm(prev.payment_term);
            const expectedDue = days !== null && prev.order_date ? addDaysToDate(prev.order_date, days) : null;
            const updatedTerm = expectedDue && expectedDue !== newDueDate ? 'Custom' : prev.payment_term;

            return { ...prev, due_date: newDueDate, payment_term: updatedTerm };
        });
    };

    const summary = useMemo(() => {
        let subtotal = 0;
        let ppn = 0;

        groups.forEach((g) =>
            g.items.forEach((item) => {
                const tax = taxes.find((t) => t.id === item.tax_id);
                const rate = tax ? Number(tax.rate) : 0;
                const { lineSubtotal, lineTax } = getLineTotals(
                    { qty: Number(item.qty || 0), unitPrice: Number(item.unit_price || 0), taxRate: rate },
                    data.is_tax_inclusive,
                );
                subtotal += lineSubtotal;
                ppn += lineTax;
            }),
        );

        return { subtotal, ppn, total: subtotal + ppn };
    }, [groups, data.is_tax_inclusive, taxes]);

    const groupedSummary = useMemo(() => {
        return sortedGroups.map((g) => {
            const branch = branches.find((b) => String(b.id) === String(g.destination_branch_id));
            let subtotal = 0;

            g.items.forEach((item) => {
                const tax = taxes.find((t) => t.id === item.tax_id);
                const rate = tax ? Number(tax.rate) : 0;
                const { lineTotal } = getLineTotals(
                    { qty: Number(item.qty || 0), unitPrice: Number(item.unit_price || 0), taxRate: rate },
                    data.is_tax_inclusive,
                );
                subtotal += lineTotal;
            });

            return {
                uid: g.uid,
                branchName: branch?.name ?? `Cabang ${g.destination_branch_id}`,
                branchCode: branch?.code ?? '',
                date: g.destination_expected_date,
                subtotal,
                count: g.items.length,
            };
        });
    }, [sortedGroups, branches, taxes, data.is_tax_inclusive]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        transform((prev) => ({
            ...prev,
            items: flatItems as unknown as never[],
        }));
        post('/purchasing/orders');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Pesanan Pembelian" />
            <div className="w-full space-y-6">
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs">
                        <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                            <div className="lg:col-span-4">
                                <SupplierComboBox
                                    suppliers={suppliers}
                                    value={data.supplier_id}
                                    inputValue={supplierInputValue}
                                    onInputChange={setSupplierInputValue}
                                    onChange={(selectedId) => {
                                        setData('supplier_id', selectedId ?? '');
                                        const s = suppliers.find((sup) => sup.id === selectedId);

                                        if (s) {
                                            setSupplierInputValue(s.name);
                                            setData((prev) => ({ ...prev, supplier_id: s.id, supplier_email: s.email || '', billing_address: s.address || '' }));
                                        } else {
                                            setSupplierInputValue('');
                                        }
                                    }}
                                    error={errors.supplier_id}
                                />
                                {isHoOnlySupplier && (
                                    <div className="mt-3 rounded-lg border border-warning-300 bg-warning-50 p-3 dark:bg-warning-500/10">
                                        <div className="flex items-start gap-2">
                                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning-600" />
                                            <div className="flex-1">
                                                <p className="text-sm font-medium text-warning-800 dark:text-warning-200">Supplier ini hanya bisa dipesan oleh Head Office.</p>
                                                <p className="mt-1 text-xs text-warning-700 dark:text-warning-300">Silakan buat Stock Request terlebih dahulu.</p>
                                            </div>
                                            <Link href="/warehouse/stock-requests/create" className="inline-flex items-center gap-1 rounded-md bg-warning-600 px-3 py-1.5 text-xs font-medium whitespace-nowrap text-white hover:bg-warning-700">
                                                Buat Stock Request <ArrowRight className="size-3" />
                                            </Link>
                                        </div>
                                    </div>
                                )}
                            </div>
                            <div className="lg:col-span-5">
                                <label className="mb-1 block text-xs font-semibold text-foreground">Email</label>
                                <input
                                    type="email"
                                    placeholder="e.g. john@example.com"
                                    value={data.supplier_email}
                                    onChange={(e) => setData('supplier_email', e.target.value)}
                                    className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                />
                                {errors.supplier_email && <p className="mt-1 text-xs text-danger">{errors.supplier_email}</p>}
                            </div>
                            <div className="pt-2 text-right lg:col-span-3 lg:pt-0">
                                <p className="text-2xl font-black text-foreground">Total {formatCurrency(summary.total)}</p>
                                {groupedSummary.length > 1 && (
                                    <div className="mt-2 space-y-1 text-xs text-muted">
                                        {groupedSummary.map((g) => (
                                            <div key={g.uid} className="flex justify-between gap-2">
                                                <span>
                                                    {g.branchCode} — {g.branchName}
                                                </span>
                                                <span>{formatCurrency(g.subtotal)}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="border-t border-dashed border-border" />

                        <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                            <div className="lg:col-span-3">
                                <label className="mb-1 block text-xs font-semibold text-foreground">Alamat penagihan</label>
                                <textarea
                                    rows={8}
                                    placeholder="e.g. Jalan Indonesia Blok C No. 22"
                                    value={data.billing_address}
                                    onChange={(e) => setData('billing_address', e.target.value)}
                                    className="h-[180px] w-full resize-none rounded-lg border border-border bg-surface p-3 text-sm text-foreground focus:border-accent focus:ring-accent"
                                />
                                {errors.billing_address && <p className="mt-1 text-xs text-danger">{errors.billing_address}</p>}
                            </div>
                            <div className="space-y-3 lg:col-span-3">
                                <div>
                                    <FormDatePicker label="Tgl. Transaksi" value={data.order_date} onChange={handleOrderDateChange} />
                                    {errors.order_date && <p className="mt-1 text-xs text-danger">{errors.order_date}</p>}
                                </div>
                                <div>
                                    <FormDatePicker label="Tgl. Jatuh Tempo" value={data.due_date} onChange={handleDueDateChange} />
                                    {errors.due_date && <p className="mt-1 text-xs text-danger">{errors.due_date}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">Syarat pembayaran</label>
                                    <select
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                        value={data.payment_term}
                                        onChange={(e) => handlePaymentTermChange(e.target.value)}
                                    >
                                        {paymentTerms.map((pt) => (
                                            <option key={pt.id} value={pt.id}>
                                                {pt.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="space-y-3 lg:col-span-3">
                                <div>
                                    <div className="mb-1 flex items-center gap-1">
                                        <label className="block text-xs font-semibold text-foreground">No Transaksi</label>
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
                                    <label className="mb-1 block text-xs font-semibold text-foreground">Nomor referensi supplier</label>
                                    <input
                                        type="text"
                                        value={data.supplier_reference}
                                        onChange={(e) => setData('supplier_reference', e.target.value)}
                                        className="w-full rounded-lg border border-border bg-surface px-3 py-2 text-sm text-foreground focus:border-accent focus:ring-accent"
                                    />
                                    {errors.supplier_reference && <p className="mt-1 text-xs text-danger">{errors.supplier_reference}</p>}
                                </div>
                                <div>
                                    <label className="mb-1 block text-xs font-semibold text-foreground">Gudang HQ Penerima (Regular)</label>
                                    <div className="w-full rounded-lg border border-border bg-surface-secondary/40 px-3 py-2 text-sm text-foreground">
                                        {hqWarehouse ? `${hqWarehouse.code} — ${hqWarehouse.name}` : 'GD-HQ-REG belum tersedia'}
                                    </div>
                                    {errors.warehouse_id && <p className="mt-1 text-xs text-danger">{errors.warehouse_id}</p>}
                                </div>
                            </div>
                            <div className="space-y-3 lg:col-span-3">
                                <div>
                                    <TagComboBox
                                        tags={allTags}
                                        selectedTagIds={data.tag_ids}
                                        onSelectedTagsChange={(tagIds) => setData('tag_ids', tagIds.map(Number))}
                                        onCreateTag={handleCreateTag}
                                        placeholder="Pilih atau cari tag"
                                    />
                                    {errors.tag_ids && <p className="mt-1 text-xs text-danger">{errors.tag_ids as string}</p>}
                                </div>
                                <div className="rounded-lg border border-border bg-surface-secondary/30 p-3">
                                    <p className="text-xs font-semibold text-foreground">Cabang Pemilik</p>
                                    <p className="text-sm font-bold text-accent">
                                        {currentBranch?.name} ({currentBranch?.code}) — HQ
                                    </p>
                                    <p className="mt-1 text-xs text-muted">Alokasi per cabang di bawah, urut tanggal kirim tercepat di atas.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h3 className="text-sm font-bold text-foreground">Alokasi per Cabang</h3>
                                <p className="text-xs text-muted">
                                    {groups.length} cabang • {totalAllocations} alokasi • urut tanggal kirim tercepat di atas
                                </p>
                            </div>
                            <label className="flex cursor-pointer items-center gap-2 text-xs">
                                <input
                                    type="checkbox"
                                    checked={data.is_tax_inclusive}
                                    onChange={(e) => setData('is_tax_inclusive', e.target.checked)}
                                    className="rounded border-border"
                                />
                                Harga termasuk pajak
                            </label>
                        </div>

                        <div className="space-y-3">
                            {sortedGroups.map((group, groupIdx) => {
                                const branch = branches.find((b) => String(b.id) === String(group.destination_branch_id));
                                const warehouse = warehousesByBranch[String(group.destination_branch_id)]?.[0];
                                const variantsForBranch = productVariants.filter((v) => v.branch_id === Number(group.destination_branch_id));
                                const variantsToShow = variantsForBranch.length > 0 ? variantsForBranch : productVariants;
                                const isExpanded = expanded.has(group.uid);
                                const flatOffset = sortedGroups.slice(0, groupIdx).reduce((acc, g) => acc + g.items.length, 0);
                                const dateErrorKey = Object.keys(errors).find((k) => k.startsWith(`items.${flatOffset}`) && k.includes('destination_expected_date'));
                                const dateError = dateErrorKey ? String(errors[dateErrorKey as keyof typeof errors]) : undefined;

                                return (
                                    <BranchAllocationCard
                                        key={group.uid}
                                        group={group}
                                        branch={branch}
                                        warehouse={warehouse}
                                        variants={variantsToShow}
                                        taxes={taxes}
                                        isTaxInclusive={data.is_tax_inclusive}
                                        isExpanded={isExpanded}
                                        canRemoveGroup={true}
                                        dateError={dateError}
                                        onToggle={() => toggleGroup(group.uid)}
                                        onRemoveGroup={() => removeBranchGroup(group.uid)}
                                        onChangeDate={(newDate) => changeGroupDate(group.uid, newDate)}
                                        onAddItem={() => addItemToGroup(group.uid)}
                                        onUpdateItem={(itemUid, field, value) => updateItemInGroup(group.uid, itemUid, field as never, value)}
                                        onRemoveItem={(itemUid) => removeItemFromGroup(group.uid, itemUid)}
                                    />
                                );
                            })}
                        </div>

                        <AddBranchBar availableBranches={availableBranches} initialDate={todayStr} onAdd={addBranchGroup} error={errors.items ? String(errors.items) : undefined} />

                        {flatItems.length === 0 && <p className="px-4 py-6 text-center text-sm text-muted">Belum ada alokasi. Tambah cabang di bawah.</p>}
                    </div>

                    <div className="grid grid-cols-1 gap-6 rounded-xl border border-border bg-surface p-6 shadow-xs lg:grid-cols-12">
                        <div className="space-y-2 lg:col-span-7">
                            <label className="block text-xs font-semibold text-foreground">Memo / Catatan Internal</label>
                            <TextArea fullWidth rows={4} placeholder="Masukkan pesan, catatan khusus supplier, atau instruksi pengiriman..." value={data.note} onChange={(e) => setData('note', e.target.value)} />
                            {errors.note && <p className="mt-1 text-xs text-danger">{errors.note}</p>}
                        </div>
                        <div className="flex flex-col justify-between rounded-xl border border-border/60 bg-surface-secondary/40 p-4 lg:col-span-5">
                            <h3 className="mb-3 border-b border-border/40 pb-2 text-xs font-bold tracking-wider text-muted uppercase">Ringkasan Pembayaran</h3>
                            <div className="space-y-2.5 text-sm">
                                <div className="flex justify-between text-muted">
                                    <span>Subtotal</span>
                                    <span className="font-medium text-foreground">{formatCurrency(summary.subtotal)}</span>
                                </div>
                                <div className="flex justify-between text-muted">
                                    <span>Pajak PPN</span>
                                    <span className="font-medium text-foreground">{formatCurrency(summary.ppn)}</span>
                                </div>
                                <div className="flex justify-between border-t border-border pt-3 text-base font-bold text-foreground">
                                    <span>Total Akhir</span>
                                    <span className="text-lg text-accent">{formatCurrency(summary.total)}</span>
                                </div>
                                {groupedSummary.length > 1 && (
                                    <div className="space-y-1 pt-2 text-xs">
                                        {groupedSummary.map((g) => (
                                            <div key={g.uid} className="flex justify-between gap-2 text-muted">
                                                <span>
                                                    {g.branchCode} • {g.date}
                                                </span>
                                                <span>{formatCurrency(g.subtotal)}</span>
                                            </div>
                                        ))}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="flex items-center justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/purchasing/orders">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button type="submit" variant="primary" isDisabled={processing || isHoOnlySupplier}>
                            Simpan Pesanan Pembelian
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
