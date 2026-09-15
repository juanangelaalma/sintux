import { Button, Checkbox } from '@heroui/react';
import { MinusCircle, Plus } from 'lucide-react';
import { formatCurrency } from '@/lib/format';
import { calculateInvoiceTotals } from './totals';
import type {
    DiscountType,
    SalesLineItemRow,
    SaleTaxOption,
    SaleVariantOption,
} from './types';

type InvoiceItemsEditorProps = {
    items: SalesLineItemRow[];
    productVariants: SaleVariantOption[];
    taxes?: SaleTaxOption[];
    isTaxInclusive?: boolean;
    onTaxInclusiveChange?: (inclusive: boolean) => void;
    invoiceDiscountType?: DiscountType | '';
    invoiceDiscountValue?: number;
    onInvoiceDiscountTypeChange?: (type: DiscountType | '') => void;
    onInvoiceDiscountValueChange?: (value: number) => void;
    onAddItem: () => void;
    onRemoveItem: (index: number) => void;
    onUpdateItem: (
        index: number,
        field: keyof SalesLineItemRow,
        value: string | number | null,
    ) => void;
    errors?: Record<string, string>;
};

const GRID_COLS =
    'grid-cols-[minmax(170px,1.2fr)_4.5rem_4rem_minmax(110px,1fr)_minmax(150px,1.2fr)_minmax(110px,1fr)_minmax(110px,1fr)_2.5rem]';

const inputClass =
    'w-full min-w-0 rounded-lg border border-border bg-surface px-2.5 py-1.5 text-xs text-foreground focus:border-accent focus:ring-accent';

export default function InvoiceItemsEditor({
    items,
    productVariants,
    taxes = [],
    isTaxInclusive = false,
    onTaxInclusiveChange,
    invoiceDiscountType = '',
    invoiceDiscountValue = 0,
    onInvoiceDiscountTypeChange,
    onInvoiceDiscountValueChange,
    onAddItem,
    onRemoveItem,
    onUpdateItem,
    errors = {},
}: InvoiceItemsEditorProps) {
    const totals = calculateInvoiceTotals(
        items,
        taxes,
        invoiceDiscountType,
        invoiceDiscountValue,
        isTaxInclusive,
    );

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between pr-1">
                <span className="text-xs font-semibold text-foreground">
                    Item Penjualan
                </span>
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
                    <div
                        className={`grid ${GRID_COLS} min-w-[1080px] items-center gap-3 border-b border-border bg-surface-secondary/40 px-3 py-2.5 text-xs font-semibold text-foreground`}
                    >
                        <span>Produk</span>
                        <span>Qty</span>
                        <span>Unit</span>
                        <span className="text-right">Harga</span>
                        <span>Diskon</span>
                        <span>Pajak</span>
                        <span className="text-right">Jumlah</span>
                        <span></span>
                    </div>

                    <div className="min-w-[1080px] divide-y divide-border/60">
                        {items.map((row, idx) => {
                            const selectedVariant = productVariants.find(
                                (v) => v.id === row.product_variant_id,
                            );
                            const computed = totals.lines[idx];

                            return (
                                <div
                                    key={idx}
                                    className="group transition-colors hover:bg-surface-secondary/30"
                                >
                                    <div
                                        className={`grid ${GRID_COLS} items-center gap-3 px-3 py-2.5`}
                                    >
                                        <div className="min-w-0">
                                            <select
                                                aria-label="Produk"
                                                className={`${inputClass} truncate`}
                                                value={String(
                                                    row.product_variant_id,
                                                )}
                                                onChange={(e) => {
                                                    const variantId = Number(
                                                        e.target.value,
                                                    );
                                                    const variant =
                                                        productVariants.find(
                                                            (v) =>
                                                                v.id ===
                                                                variantId,
                                                        );
                                                    onUpdateItem(
                                                        idx,
                                                        'product_variant_id',
                                                        variantId,
                                                    );

                                                    if (variant) {
                                                        onUpdateItem(
                                                            idx,
                                                            'unit_price',
                                                            Number(
                                                                variant.selling_price ||
                                                                    0,
                                                            ),
                                                        );
                                                    }
                                                }}
                                            >
                                                {productVariants.map((v) => (
                                                    <option
                                                        key={v.id}
                                                        value={v.id}
                                                        title={`${v.product_name} (${v.sku})`}
                                                    >
                                                        {v.product_name}
                                                    </option>
                                                ))}
                                            </select>
                                            {errors[
                                                `items.${idx}.product_variant_id`
                                            ] && (
                                                <p className="mt-1 text-[11px] text-danger">
                                                    {
                                                        errors[
                                                            `items.${idx}.product_variant_id`
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div className="min-w-0">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                aria-label="Kuantitas"
                                                className={inputClass}
                                                value={row.qty}
                                                onChange={(e) =>
                                                    onUpdateItem(
                                                        idx,
                                                        'qty',
                                                        Number(e.target.value),
                                                    )
                                                }
                                            />
                                            {errors[`items.${idx}.qty`] && (
                                                <p className="mt-1 text-[11px] text-danger">
                                                    {errors[`items.${idx}.qty`]}
                                                </p>
                                            )}
                                        </div>

                                        <div className="min-w-0">
                                            <div className="flex w-full items-center justify-between rounded-lg border border-border bg-surface-secondary/50 px-2.5 py-1.5 text-xs text-muted">
                                                <span>
                                                    {selectedVariant?.uom_name?.toLowerCase() ||
                                                        'pcs'}
                                                </span>
                                            </div>
                                        </div>

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

                                        <div className="min-w-0">
                                            <div className="flex overflow-hidden rounded-lg border border-border bg-surface focus-within:border-accent">
                                                <select
                                                    aria-label="Tipe diskon"
                                                    className="shrink-0 border-r border-border bg-surface-secondary/60 px-1.5 py-1.5 text-xs text-foreground outline-none"
                                                    value={
                                                        row.discount_type || ''
                                                    }
                                                    onChange={(e) =>
                                                        onUpdateItem(
                                                            idx,
                                                            'discount_type',
                                                            (e.target.value ||
                                                                '') as
                                                                | DiscountType
                                                                | '',
                                                        )
                                                    }
                                                >
                                                    <option value="">-</option>
                                                    <option value="percent">
                                                        %
                                                    </option>
                                                    <option value="nominal">
                                                        Rp
                                                    </option>
                                                </select>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    aria-label="Nilai diskon"
                                                    disabled={
                                                        !row.discount_type
                                                    }
                                                    className="w-full min-w-0 border-none bg-transparent px-2 py-1.5 text-right text-xs text-foreground outline-none focus:ring-0 disabled:bg-surface-secondary/50 disabled:text-muted"
                                                    value={
                                                        row.discount_value ?? 0
                                                    }
                                                    onChange={(e) =>
                                                        onUpdateItem(
                                                            idx,
                                                            'discount_value',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </div>
                                            {errors[
                                                `items.${idx}.discount_value`
                                            ] && (
                                                <p className="mt-1 text-[11px] text-danger">
                                                    {
                                                        errors[
                                                            `items.${idx}.discount_value`
                                                        ]
                                                    }
                                                </p>
                                            )}
                                        </div>

                                        <div className="min-w-0">
                                            <select
                                                aria-label="Pajak"
                                                className={`${inputClass} truncate`}
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
                                                <option value="">-</option>
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

                                        <div className="flex min-w-0 items-center overflow-hidden rounded-lg border border-border bg-surface-secondary/40">
                                            <span className="shrink-0 border-r border-border bg-surface-secondary/80 px-2 py-1.5 text-xs font-medium text-muted">
                                                Rp
                                            </span>
                                            <div className="w-full truncate px-2 py-1.5 text-right text-xs font-medium text-foreground">
                                                {formatCurrency(
                                                    computed?.line_total ?? 0,
                                                )
                                                    .replace('Rp', '')
                                                    .trim()}
                                            </div>
                                        </div>

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
                        {items.length} item{items.length > 1 ? 's' : ''} · Total
                        Qty{' '}
                        {items.reduce(
                            (sum, item) => sum + Number(item.qty || 0),
                            0,
                        )}
                    </span>
                </div>
            </div>

            <div className="flex justify-end">
                <div className="w-80 space-y-1.5 rounded-xl border border-border bg-surface p-4 text-sm">
                    <div className="flex justify-between text-muted">
                        <span>Subtotal</span>
                        <span>{formatCurrency(totals.subtotal)}</span>
                    </div>
                    <div className="flex justify-between text-muted">
                        <span>Diskon Perbaris</span>
                        <span>
                            {formatCurrency(totals.line_discount_total)}
                        </span>
                    </div>
                    <div className="flex justify-between text-muted">
                        <span>Total setelah Diskon Perbaris</span>
                        <span>
                            {formatCurrency(totals.net_after_line_discount)}
                        </span>
                    </div>
                    <div className="flex items-center justify-between gap-2 border-t border-border/60 pt-2">
                        <span className="text-muted">Disk</span>
                        <div className="flex w-44 overflow-hidden rounded-lg border border-border bg-surface focus-within:border-accent">
                            <select
                                aria-label="Tipe diskon invoice"
                                className="shrink-0 border-r border-border bg-surface-secondary/60 px-1.5 py-1.5 text-xs text-foreground outline-none"
                                value={invoiceDiscountType}
                                onChange={(e) =>
                                    onInvoiceDiscountTypeChange?.(
                                        (e.target.value || '') as
                                            DiscountType | '',
                                    )
                                }
                            >
                                <option value="">-</option>
                                <option value="percent">%</option>
                                <option value="nominal">Rp</option>
                            </select>
                            <input
                                type="number"
                                min="0"
                                aria-label="Nilai diskon invoice"
                                disabled={!invoiceDiscountType}
                                className="w-full min-w-0 border-none bg-transparent px-2 py-1.5 text-right text-xs text-foreground outline-none focus:ring-0 disabled:bg-surface-secondary/50 disabled:text-muted"
                                value={invoiceDiscountValue}
                                onChange={(e) =>
                                    onInvoiceDiscountValueChange?.(
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </div>
                    </div>
                    <div className="flex justify-between font-semibold text-foreground">
                        <span>Total (after disc)</span>
                        <span>
                            {formatCurrency(
                                totals.net_after_line_discount -
                                    totals.invoice_discount_amount,
                            )}
                        </span>
                    </div>
                    <div className="flex justify-between text-muted">
                        <span>Pajak</span>
                        <span>{formatCurrency(totals.tax_amount)}</span>
                    </div>
                    <div className="flex justify-between border-t border-border pt-2 text-base font-bold text-foreground">
                        <span>TOTAL</span>
                        <span>{formatCurrency(totals.total)}</span>
                    </div>
                    <div className="flex justify-between text-base font-black text-foreground">
                        <span>Sisa Tagihan</span>
                        <span>{formatCurrency(totals.total)}</span>
                    </div>
                </div>
            </div>
        </div>
    );
}
