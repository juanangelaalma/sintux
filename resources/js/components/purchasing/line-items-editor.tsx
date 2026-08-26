import { Button, Checkbox } from '@heroui/react';
import { MinusCircle, Plus } from 'lucide-react';
import { formatCurrency } from '@/lib/format';

export type ProductVariant = {
    id: number;
    product_name: string;
    sku: string;
    uom_name?: string;
};

export type TaxOption = {
    id: number;
    name: string;
    rate: number;
};

export type LineItemRow = {
    product_variant_id: number;
    purchase_order_item_id?: number;
    description?: string;
    qty: number;
    unit_price: number;
    discount?: string;
    tax_id?: number | null;
};

type LineItemsEditorProps = {
    items: LineItemRow[];
    productVariants: ProductVariant[];
    taxes?: TaxOption[];
    isTaxInclusive?: boolean;
    onTaxInclusiveChange?: (inclusive: boolean) => void;
    onAddItem: () => void;
    onRemoveItem: (index: number) => void;
    onUpdateItem: (
        index: number,
        field: keyof LineItemRow,
        value: string | number | null,
    ) => void;
};

const GRID_COLS =
    'grid-cols-[minmax(180px,1.3fr)_minmax(140px,1.1fr)_5.5rem_5.5rem_minmax(120px,1fr)_minmax(110px,1fr)_minmax(120px,1fr)_2.5rem]';

export default function LineItemsEditor({
    items,
    productVariants,
    taxes = [],
    isTaxInclusive = false,
    onTaxInclusiveChange,
    onAddItem,
    onRemoveItem,
    onUpdateItem,
}: LineItemsEditorProps) {
    return (
        <div className="space-y-2">
            {/* Top Right: Checkbox Harga termasuk pajak */}
            <div className="flex justify-end pr-1">
                <Checkbox
                    isSelected={isTaxInclusive}
                    onChange={(checked: boolean) =>
                        onTaxInclusiveChange?.(checked)
                    }
                >
                    <Checkbox.Content className="cursor-pointer text-xs font-normal text-foreground">
                        <Checkbox.Control className="rounded border border-border bg-surface">
                            <Checkbox.Indicator />
                        </Checkbox.Control>
                        Harga termasuk pajak
                    </Checkbox.Content>
                </Checkbox>
            </div>

            <div className="overflow-hidden rounded-xl border border-border bg-surface shadow-xs">
                <div className="overflow-x-auto">
                    {/* Header Row */}
                    <div
                        className={`grid ${GRID_COLS} min-w-[860px] items-center gap-3 border-b border-border bg-surface-secondary/40 px-3 py-2.5 text-xs font-semibold text-foreground`}
                    >
                        <span>Produk</span>
                        <span>Deskripsi</span>
                        <span>Kuantitas</span>
                        <span>Unit</span>
                        <span className="text-right">Harga satuan</span>
                        <span>Pajak</span>
                        <span className="text-right">Jumlah</span>
                        <span></span>
                    </div>

                    {/* Table Body */}
                    <div className="min-w-[860px] divide-y divide-border/60">
                        {items.map((row, idx) => {
                            const selectedVariant = productVariants.find(
                                (v) => v.id === row.product_variant_id,
                            );
                            const selectedTax = taxes.find(
                                (t) => t.id === row.tax_id,
                            );
                            const taxRate = selectedTax
                                ? Number(selectedTax.rate)
                                : 0;
                            const qty = Number(row.qty || 0);
                            const price = Number(row.unit_price || 0);
                            const lineGross = qty * price;

                            let lineDisplayTotal = lineGross;

                            if (isTaxInclusive && taxRate > 0) {
                                lineDisplayTotal = lineGross;
                            } else {
                                lineDisplayTotal =
                                    lineGross * (1 + taxRate / 100);
                            }

                            return (
                                <div
                                    key={idx}
                                    className="group transition-colors hover:bg-surface-secondary/30"
                                >
                                    <div
                                        className={`grid ${GRID_COLS} items-center gap-3 px-3 py-2.5`}
                                    >
                                        {/* Produk */}
                                        <div className="min-w-0">
                                            <select
                                                aria-label="Produk"
                                                className="w-full min-w-0 truncate rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent"
                                                value={String(
                                                    row.product_variant_id,
                                                )}
                                                onChange={(e) =>
                                                    onUpdateItem(
                                                        idx,
                                                        'product_variant_id',
                                                        Number(e.target.value),
                                                    )
                                                }
                                            >
                                                {productVariants.map((v) => (
                                                    <option
                                                        key={v.id}
                                                        value={v.id}
                                                        title={v.product_name}
                                                    >
                                                        {v.product_name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* Deskripsi */}
                                        <div className="min-w-0">
                                            <input
                                                type="text"
                                                placeholder="Masukkan keterangan"
                                                aria-label="Deskripsi"
                                                className="w-full min-w-0 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground placeholder:text-muted focus:border-accent focus:ring-accent"
                                                value={row.description || ''}
                                                onChange={(e) =>
                                                    onUpdateItem(
                                                        idx,
                                                        'description',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>

                                        {/* Kuantitas */}
                                        <div className="min-w-0">
                                            <input
                                                type="number"
                                                min="1"
                                                aria-label="Kuantitas"
                                                className="w-full min-w-0 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent"
                                                value={row.qty}
                                                onChange={(e) =>
                                                    onUpdateItem(
                                                        idx,
                                                        'qty',
                                                        Number(e.target.value),
                                                    )
                                                }
                                            />
                                        </div>

                                        {/* Unit (UOM) */}
                                        <div className="min-w-0">
                                            <div className="flex w-full items-center justify-between rounded-lg border border-border bg-surface-secondary/50 px-2.5 py-1.5 text-xs text-muted">
                                                <span>
                                                    {selectedVariant?.uom_name?.toLowerCase() ||
                                                        'pcs'}
                                                </span>
                                            </div>
                                        </div>

                                        {/* Harga Satuan */}
                                        <div className="flex min-w-0 overflow-hidden rounded-lg border border-border bg-surface focus-within:border-accent">
                                            <span className="shrink-0 border-r border-border bg-surface-secondary/60 px-2 py-1.5 text-xs font-medium text-muted">
                                                Rp
                                            </span>
                                            <input
                                                type="number"
                                                min="0"
                                                aria-label="Harga satuan"
                                                className="w-full min-w-0 border-none bg-transparent px-2 py-1.5 text-right text-xs text-foreground outline-none focus:ring-0"
                                                value={row.unit_price}
                                                onChange={(e) =>
                                                    onUpdateItem(
                                                        idx,
                                                        'unit_price',
                                                        Number(e.target.value),
                                                    )
                                                }
                                            />
                                        </div>

                                        {/* Pajak */}
                                        <div className="min-w-0">
                                            <select
                                                aria-label="Pajak"
                                                className="w-full min-w-0 truncate rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent"
                                                value={
                                                    row.tax_id
                                                        ? String(row.tax_id)
                                                        : ''
                                                }
                                                onChange={(e) =>
                                                    onUpdateItem(
                                                        idx,
                                                        'tax_id',
                                                        e.target.value
                                                            ? Number(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Pilih pajak
                                                </option>
                                                {taxes.map((t) => (
                                                    <option
                                                        key={t.id}
                                                        value={t.id}
                                                    >
                                                        {t.name} ({t.rate}%)
                                                    </option>
                                                ))}
                                            </select>
                                        </div>

                                        {/* Jumlah (Line Gross/Net) */}
                                        <div className="flex min-w-0 items-center overflow-hidden rounded-lg border border-border bg-surface-secondary/40">
                                            <span className="shrink-0 border-r border-border bg-surface-secondary/80 px-2 py-1.5 text-xs font-medium text-muted">
                                                Rp
                                            </span>
                                            <div className="w-full truncate px-2 py-1.5 text-right text-xs font-medium text-foreground">
                                                {formatCurrency(
                                                    lineDisplayTotal,
                                                )
                                                    .replace('Rp', '')
                                                    .trim()}
                                            </div>
                                        </div>

                                        {/* Action Delete */}
                                        <div className="flex items-center justify-center">
                                            {items.length > 1 && (
                                                <button
                                                    type="button"
                                                    aria-label="Hapus item"
                                                    onClick={() =>
                                                        onRemoveItem(idx)
                                                    }
                                                    className="rounded p-1 text-muted transition-colors hover:text-danger"
                                                >
                                                    <MinusCircle className="size-4" />
                                                </button>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* Bottom Bar: Tambah Baris + Count Item */}
                <div className="flex items-center justify-between border-t border-border bg-surface p-3">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        className="gap-1.5 text-xs font-medium"
                        onPress={onAddItem}
                    >
                        <Plus className="size-3.5" />
                        Tambah Baris Data
                    </Button>
                    <span className="text-xs text-muted">
                        {items.length} item{items.length > 1 ? 's' : ''}
                    </span>
                </div>
            </div>
        </div>
    );
}
