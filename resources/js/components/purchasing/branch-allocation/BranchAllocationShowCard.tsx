import { Building2, CalendarDays, ChevronDown, ChevronUp, Package } from 'lucide-react';
import { formatCurrency, formatDate } from '@/lib/format';

type BranchRef = { id: number; name: string; code: string };
type WarehouseRef = { id: number; code: string; name: string };

type Item = {
    id: number;
    product_name: string;
    sku: string;
    qty_ordered: number;
    qty_received: number;
    unit_price: number;
    line_total: number;
};

type Props = {
    branchCode: string;
    branch?: BranchRef | null;
    warehouse?: WarehouseRef | null;
    date: string | null;
    items: Item[];
    subtotal: number;
    isExpanded: boolean;
    onToggle: () => void;
};

export function BranchAllocationShowCard({ branchCode, branch, warehouse, date, items, subtotal, isExpanded, onToggle }: Props) {
    const dateLabel = date ? formatDate(date) : '-';

    return (
        <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-xs">
            <button
                type="button"
                onClick={onToggle}
                aria-expanded={isExpanded}
                aria-controls={`branch-group-${branchCode}`}
                className="flex w-full flex-wrap items-center justify-between gap-3 bg-surface-secondary/40 px-4 py-3 text-left hover:bg-surface-secondary/60"
            >
                <span className="flex min-w-0 flex-1 items-center gap-3">
                    <span className="flex size-8 items-center justify-center rounded-lg bg-accent/10 text-accent" aria-hidden>
                        <Building2 className="size-4" />
                    </span>
                    <span className="min-w-0">
                        <span className="flex flex-wrap items-center gap-2 text-xs font-bold text-foreground">
                            <span>
                                {branchCode} — {branch?.name ?? ''}
                            </span>
                            <span className="inline-flex items-center gap-1 rounded-full border border-border bg-surface px-2.5 py-0.5 text-xs font-medium text-muted">
                                <Package className="size-3" aria-hidden />
                                {warehouse ? warehouse.code : '—'}
                            </span>
                            <span className="inline-flex items-center gap-1 rounded-full bg-accent/10 px-2.5 py-0.5 text-xs font-medium text-accent">
                                {items.length} produk
                            </span>
                        </span>
                        <span className="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-muted">
                            <CalendarDays className="size-3.5" aria-hidden />
                            Tgl Kirim: {dateLabel}
                            <span aria-hidden>•</span>
                            <span>{formatCurrency(subtotal)}</span>
                        </span>
                    </span>
                </span>
                <span className="inline-flex items-center gap-1 text-muted">
                    {isExpanded ? <ChevronUp className="size-4" aria-hidden /> : <ChevronDown className="size-4" aria-hidden />}
                    <span className="sr-only">{isExpanded ? 'Ciutkan' : 'Bentangkan'} cabang {branchCode}</span>
                </span>
            </button>

            {isExpanded && (
                <div id={`branch-group-${branchCode}`} className="border-t border-border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm text-foreground">
                            <thead className="border-b border-border bg-surface-secondary/30 text-xs font-semibold text-foreground">
                                <tr>
                                    <th className="px-4 py-2.5">Produk</th>
                                    <th className="px-4 py-2.5">SKU</th>
                                    <th className="px-4 py-2.5 text-right">Dipesan</th>
                                    <th className="px-4 py-2.5 text-right">Diterima</th>
                                    <th className="px-4 py-2.5 text-right">Harga Satuan</th>
                                    <th className="px-4 py-2.5 text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {items.map((item) => (
                                    <tr key={item.id} className="hover:bg-surface-secondary/30">
                                        <td className="px-4 py-3 font-medium text-foreground">{item.product_name}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-muted">{item.sku}</td>
                                        <td className="px-4 py-3 text-right font-medium">{item.qty_ordered}</td>
                                        <td className="px-4 py-3 text-right font-medium">{item.qty_received}</td>
                                        <td className="px-4 py-3 text-right">{formatCurrency(item.unit_price)}</td>
                                        <td className="px-4 py-3 text-right font-bold text-foreground">{formatCurrency(item.line_total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="flex items-center justify-end border-t border-border bg-surface px-4 py-2.5">
                        <span className="text-xs text-muted">
                            {branchCode} • kirim {dateLabel} • {formatCurrency(subtotal)}
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}
