import { MinusCircle } from 'lucide-react';
import { formatCurrency } from '@/lib/format';
import { getLineTotals } from '@/lib/purchasing/calc';
import type { BranchItem, ProductVariant } from './types';

type TaxOption = { id: number; name: string; rate: number };

type Props = {
    branchCode: string;
    row: BranchItem;
    variants: ProductVariant[];
    taxes: TaxOption[];
    isTaxInclusive: boolean;
    canRemove: boolean;
    onChange: (field: keyof BranchItem, value: string | number | null) => void;
    onRemove: () => void;
};

export function BranchProductRow({ branchCode, row, variants, taxes, isTaxInclusive, canRemove, onChange, onRemove }: Props) {
    const selectedTax = taxes.find((t) => t.id === row.tax_id);
    const taxRate = selectedTax ? Number(selectedTax.rate) : 0;
    const { lineTotal } = getLineTotals({ qty: Number(row.qty || 0), unitPrice: Number(row.unit_price || 0), taxRate }, isTaxInclusive);

    return (
        <div className="grid grid-cols-[minmax(180px,1.5fr)_6rem_minmax(130px,1fr)_minmax(110px,1fr)_minmax(120px,1fr)_2.5rem] items-center gap-3 px-3 py-2.5 hover:bg-surface-secondary/20">
            <select
                aria-label={`Produk untuk cabang ${branchCode}`}
                value={String(row.product_variant_id)}
                onChange={(e) => onChange('product_variant_id', Number(e.target.value))}
                className="w-full truncate rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent"
            >
                {variants.map((v) => (
                    <option key={v.id} value={v.id} title={v.product_name}>
                        {v.product_name} ({v.sku})
                    </option>
                ))}
            </select>
            <input
                aria-label={`Kuantitas untuk cabang ${branchCode}`}
                type="number"
                min={1}
                value={row.qty}
                onChange={(e) => onChange('qty', Number(e.target.value))}
                className="w-full rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent"
            />
            <div className="flex overflow-hidden rounded-lg border border-border bg-surface focus-within:border-accent">
                <span className="shrink-0 border-r border-border bg-surface-secondary/60 px-2 py-1.5 text-xs font-medium text-muted">Rp</span>
                <input
                    aria-label={`Harga satuan untuk cabang ${branchCode}`}
                    type="number"
                    min={0}
                    value={row.unit_price}
                    onChange={(e) => onChange('unit_price', Number(e.target.value))}
                    className="w-full border-none bg-transparent px-2 py-1.5 text-right text-xs text-foreground outline-none"
                />
            </div>
            <select
                aria-label={`Pajak untuk cabang ${branchCode}`}
                value={row.tax_id ? String(row.tax_id) : ''}
                onChange={(e) => onChange('tax_id', e.target.value ? Number(e.target.value) : null)}
                className="w-full truncate rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent"
            >
                <option value="">Tanpa pajak</option>
                {taxes.map((t) => (
                    <option key={t.id} value={t.id}>
                        {t.name} ({t.rate}%)
                    </option>
                ))}
            </select>
            <div className="flex items-center overflow-hidden rounded-lg border border-border bg-surface-secondary/40">
                <span className="shrink-0 border-r border-border bg-surface-secondary/80 px-2 py-1.5 text-xs font-medium text-muted">Rp</span>
                <div className="w-full truncate px-2 py-1.5 text-right text-xs font-medium text-foreground">
                    {formatCurrency(lineTotal).replace('Rp', '').trim()}
                </div>
            </div>
            <button
                type="button"
                aria-label={`Hapus produk dari cabang ${branchCode}`}
                onClick={onRemove}
                disabled={!canRemove}
                className="rounded p-1 text-muted transition-colors hover:text-danger disabled:opacity-30 disabled:cursor-not-allowed"
            >
                <MinusCircle className="size-4" aria-hidden />
            </button>
        </div>
    );
}
