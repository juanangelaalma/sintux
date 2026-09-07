import { Button } from '@heroui/react';
import { Building2, CalendarDays, ChevronDown, ChevronUp, CircleAlert, Package, Plus, Trash2 } from 'lucide-react';
import { formatCurrency } from '@/lib/format';
import { getLineTotals } from '@/lib/purchasing/calc';
import { BranchProductRow } from './BranchProductRow';
import type { Branch, BranchGroup, ProductVariant, Warehouse } from './types';

type TaxOption = { id: number; name: string; rate: number };

type Props = {
    group: BranchGroup;
    branch?: Branch;
    warehouse?: Warehouse;
    variants: ProductVariant[];
    taxes: TaxOption[];
    isTaxInclusive: boolean;
    isExpanded: boolean;
    canRemoveGroup: boolean;
    dateError?: string;
    onToggle: () => void;
    onRemoveGroup: () => void;
    onChangeDate: (newDate: string) => void;
    onAddItem: () => void;
    onUpdateItem: (itemUid: string, field: 'product_variant_id' | 'qty' | 'unit_price' | 'tax_id' | 'description', value: string | number | null) => void;
    onRemoveItem: (itemUid: string) => void;
};

export function BranchAllocationCard({
    group,
    branch,
    warehouse,
    variants,
    taxes,
    isTaxInclusive,
    isExpanded,
    canRemoveGroup,
    dateError,
    onToggle,
    onRemoveGroup,
    onChangeDate,
    onAddItem,
    onUpdateItem,
    onRemoveItem,
}: Props) {
    const groupSubtotal = group.items.reduce((acc, it) => {
        const tax = taxes.find((t) => t.id === it.tax_id);
        const rate = tax ? Number(tax.rate) : 0;
        const { lineTotal } = getLineTotals({ qty: Number(it.qty || 0), unitPrice: Number(it.unit_price || 0), taxRate: rate }, isTaxInclusive);

        return acc + lineTotal;
    }, 0);

    const hasValidProduct = group.items.some((it) => variants.some((v) => v.id === it.product_variant_id));

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-xs">
            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-border bg-surface px-4 py-3">
                <div className="flex min-w-0 flex-1 items-start gap-3">
                    <div className="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden>
                        <Building2 className="size-4" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <span className="text-sm font-bold text-foreground">
                                {branch ? `${branch.code} — ${branch.name}` : `Cabang ${group.destination_branch_id}`}
                            </span>
                            <span className="inline-flex items-center gap-1 rounded-full border border-border bg-surface px-2.5 py-0.5 text-xs font-medium text-muted">
                                <Package className="size-3" aria-hidden />
                                {warehouse ? warehouse.code : '—'}
                            </span>
                            <span className="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-accent">
                                {group.items.length} produk
                            </span>
                        </div>
                        <p className="mt-1 text-xs text-muted">
                            {branch?.name ?? ''} {branch?.name && warehouse?.name ? '•' : ''} {warehouse?.name ?? 'Gudang Regular'}
                        </p>
                    </div>
                </div>
                <div className="flex shrink-0 items-start gap-2">
                    <div className="flex flex-col items-end gap-1">
                        <div className="flex items-center gap-2">
                            <span className="hidden text-xs text-muted sm:inline">Tgl. kirim</span>
                            <div className="flex items-center gap-1.5">
                                <CalendarDays className="size-3.5 text-muted sm:hidden" aria-hidden />
                                <input
                                    aria-label={`Tanggal kirim untuk cabang ${branch?.code ?? group.destination_branch_id}`}
                                    type="date"
                                    value={group.destination_expected_date}
                                    onChange={(e) => onChangeDate(e.target.value)}
                                    className={`rounded-lg border px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent ${dateError ? 'border-danger bg-danger/5' : 'border-border bg-surface'}`}
                                />
                            </div>
                        </div>
                        {dateError && <p className="text-xs text-danger">{dateError}</p>}
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        className="min-w-0 border border-border bg-surface px-2"
                        aria-label={isExpanded ? `Ciutkan cabang ${branch?.code}` : `Bentangkan cabang ${branch?.code}`}
                        onPress={onToggle}
                    >
                        {isExpanded ? <ChevronUp className="size-4" aria-hidden /> : <ChevronDown className="size-4" aria-hidden />}
                    </Button>
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        className="min-w-0 border border-border bg-surface px-2 text-danger hover:bg-danger/10"
                        aria-label={`Hapus cabang ${branch?.code}`}
                        onPress={onRemoveGroup}
                        isDisabled={!canRemoveGroup}
                    >
                        <Trash2 className="size-4" aria-hidden />
                    </Button>
                </div>
            </div>

            {isExpanded && (
                <div>
                    <div className="overflow-x-auto">
                        <div className="grid min-w-[860px] grid-cols-[minmax(180px,1.5fr)_6rem_minmax(130px,1fr)_minmax(110px,1fr)_minmax(120px,1fr)_2.5rem] gap-3 bg-surface-secondary/30 px-3 py-2 text-xs font-semibold text-muted">
                            <span>Produk</span>
                            <span>Kuantitas</span>
                            <span className="text-right">Harga satuan</span>
                            <span>Pajak</span>
                            <span className="text-right">Jumlah</span>
                            <span aria-hidden />
                        </div>
                        <div className="min-w-[860px] divide-y divide-border/60">
                            {group.items.map((row) => (
                                <BranchProductRow
                                    key={row.uid}
                                    branchCode={branch?.code ?? String(group.destination_branch_id)}
                                    row={row}
                                    variants={variants}
                                    taxes={taxes}
                                    isTaxInclusive={isTaxInclusive}
                                    canRemove={group.items.length > 1}
                                    onChange={(field, value) => onUpdateItem(row.uid, field as never, value)}
                                    onRemove={() => onRemoveItem(row.uid)}
                                />
                            ))}
                        </div>
                    </div>

                    {!hasValidProduct && (
                        <div className="flex items-center gap-2 border-t border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-700 dark:border-amber-900/30 dark:bg-amber-950/20 dark:text-amber-300">
                            <CircleAlert className="size-4 shrink-0" aria-hidden />
                            <span>Belum ada produk valid ditambahkan</span>
                        </div>
                    )}

                    <div className="flex items-center justify-between border-t border-border bg-surface px-3 py-2.5">
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="gap-1.5 text-xs font-medium text-accent"
                            onPress={onAddItem}
                            aria-label={`Tambah produk untuk cabang ${branch?.code}`}
                        >
                            <Plus className="size-3.5" aria-hidden /> Tambah produk
                        </Button>
                        <span className="text-xs font-medium text-muted">
                            Subtotal cabang : <span className="font-bold text-foreground">{formatCurrency(groupSubtotal)}</span>
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}
