import { Button, Input, Label, ListBox, Select, TextArea } from '@heroui/react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { SupplierOption } from '@/components/purchasing/supplier-fields';
import { TagComboBox } from '@/components/ui/app-combobox/tags/tag-combobox';
import type { TagItem } from '@/components/ui/app-combobox/tags/tag-combobox';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';
import { getLineTotals } from '@/lib/purchasing/calc';

type WarehouseOption = { id: number; code: string; name: string };

type TransferOption = {
    id: number;
    number: string;
    from_warehouse_name: string;
};

type PrefillItem = {
    purchase_invoice_item_id: number;
    product_variant_id: number;
    product_name: string;
    sku: string;
    uom_name?: string | null;
    qty: number;
    qty_returned: number;
    remaining_qty: number;
    unit_price: number;
    tax_id: number | null;
    tax_rate: number;
    is_tracked: boolean;
};

type PrefillInvoice = {
    id: number;
    number: string;
    supplier_id: number;
    status: string;
    is_tax_inclusive: boolean;
    total: number;
    paid_amount: number;
    returned_amount: number;
};

type Props = {
    hqBranchId: number | null;
    warehouses: WarehouseOption[];
    tags: TagItem[];
    suppliers: SupplierOption[];
    prefillInvoice?: {
        invoice: PrefillInvoice;
        items: PrefillItem[];
    } | null;
    prefillError?: string | null;
    availability?: Record<string, Record<string, number>>;
    transfers?: TransferOption[];
    transfersTruncated?: boolean;
    selectedTransferId?: number | null;
};

type ReturnLine = {
    purchase_invoice_item_id: number;
    qty: number;
};

export default function PurchaseReturnsCreate({
    hqBranchId,
    warehouses,
    tags,
    suppliers,
    prefillInvoice = null,
    prefillError = null,
    availability = {},
    transfers = [],
    transfersTruncated = false,
    selectedTransferId = null,
}: Props) {
    const today = useMemo(() => new Date().toISOString().split('T')[0], []);
    const [createdTags, setCreatedTags] = useState<TagItem[]>([]);
    const allTags = useMemo(
        () => [...tags, ...createdTags],
        [tags, createdTags],
    );

    const returnable = useMemo(
        () =>
            (prefillInvoice?.items ?? []).filter(
                (item) => item.remaining_qty > 0,
            ),
        [prefillInvoice],
    );

    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        purchase_invoice_id: number | string;
        warehouse_id: number | string;
        return_transfer_id: number | string;
        return_date: string;
        message: string;
        memo: string;
        tag_ids: number[];
        items: ReturnLine[];
        attachments: File[];
    }>({
        branch_id: hqBranchId ?? '',
        supplier_id: prefillInvoice?.invoice.supplier_id ?? '',
        purchase_invoice_id: prefillInvoice?.invoice.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        return_transfer_id: selectedTransferId ?? '',
        return_date: today,
        message: '',
        memo: '',
        tag_ids: [] as number[],
        attachments: [] as File[],
        items: returnable.map((item) => ({
            purchase_invoice_item_id: item.purchase_invoice_item_id,
            qty: item.remaining_qty,
        })),
    });

    const supplierName =
        suppliers.find(
            (s) => s.id === Number(prefillInvoice?.invoice.supplier_id),
        )?.name ?? '';

    const warehouseAvailability = useMemo(
        () => availability[String(data.warehouse_id)] ?? {},
        [availability, data.warehouse_id],
    );

    const lines = useMemo(
        () =>
            data.items.map((line) => {
                const ref = returnable.find(
                    (item) =>
                        item.purchase_invoice_item_id ===
                        line.purchase_invoice_item_id,
                );
                // Produk terpantau dibatasi stok gudang terpilih; sisanya
                // hanya dibatasi sisa faktur. Validasi final di server.
                const cap = ref?.is_tracked
                    ? Math.min(
                          Number(ref?.remaining_qty ?? 0),
                          Number(
                              warehouseAvailability[
                                  String(ref?.product_variant_id)
                              ] ?? 0,
                          ),
                      )
                    : Number(ref?.remaining_qty ?? 0);
                const qty = Math.min(Number(line.qty || 0), cap);
                const totals = getLineTotals(
                    {
                        qty,
                        unitPrice: Number(ref?.unit_price ?? 0),
                        taxRate: Number(ref?.tax_rate ?? 0),
                    },
                    Boolean(prefillInvoice?.invoice.is_tax_inclusive),
                );

                return { ref, qty, cap, totals };
            }),
        [data.items, returnable, prefillInvoice, warehouseAvailability],
    );

    const estimatedTotal = lines.reduce(
        (sum, line) => sum + line.totals.lineTotal,
        0,
    );

    const handleCreateTag = async (name: string) => {
        if (!name.trim()) {
            return;
        }

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content ?? '';

        try {
            const res = await fetch('/purchasing/tags', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ name }),
            });
            const created = await res.json();

            if (res.ok && created.id) {
                setCreatedTags((prev) =>
                    prev.some((t) => t.id === created.id)
                        ? prev
                        : [...prev, { id: created.id, name: created.name }],
                );
                setData((prev) =>
                    prev.tag_ids.includes(created.id)
                        ? prev
                        : { ...prev, tag_ids: [...prev.tag_ids, created.id] },
                );
            }
        } catch {
            // noop
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/returns');
    };

    return (
        <CompanyLayout>
            <Head title="Buat Retur Pembelian" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Retur Pembelian
                        </h1>
                        {prefillInvoice && (
                            <p className="mt-1 text-sm text-muted">
                                Atas faktur #{prefillInvoice.invoice.number}
                                {supplierName ? ` — ${supplierName}` : ''}
                            </p>
                        )}
                    </div>
                    <div className="text-right">
                        <p className="text-xs font-medium text-muted">
                            Estimasi Nilai Retur
                        </p>
                        <p className="mt-0.5 text-2xl font-black text-foreground">
                            {formatCurrency(estimatedTotal)}
                        </p>
                    </div>
                </div>

                {prefillError && (
                    <div className="rounded-lg border border-rose-500/40 bg-rose-500/10 p-3 text-sm text-rose-700 dark:text-rose-300">
                        {prefillError}
                    </div>
                )}

                {!prefillInvoice && !prefillError && (
                    <div className="rounded-lg border border-border bg-surface p-6 text-sm text-muted">
                        Retur dibuat dari detail faktur pembelian: buka faktur,
                        klik{' '}
                        <span className="font-semibold text-foreground">
                            Tindakan → Retur Pembelian
                        </span>
                        .
                    </div>
                )}

                {prefillInvoice && (
                    <form
                        onSubmit={handleSubmit}
                        className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                    >
                        <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                            <div>
                                <Label className="block text-xs font-semibold text-foreground">
                                    Tanggal Retur *
                                </Label>
                                <Input
                                    type="date"
                                    value={data.return_date}
                                    max={today}
                                    onChange={(e) =>
                                        setData('return_date', e.target.value)
                                    }
                                    className="mt-1"
                                />
                                {errors.return_date && (
                                    <p className="mt-1 text-xs text-danger">
                                        {errors.return_date}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label className="block text-xs font-semibold text-foreground">
                                    Nomor Transaksi
                                </Label>
                                <Input
                                    value="Otomatis (RBL-…)"
                                    disabled
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label className="block text-xs font-semibold text-foreground">
                                    Gudang (stok keluar dari sini) *
                                </Label>
                                <Select
                                    fullWidth
                                    placeholder="Pilih gudang..."
                                    value={
                                        data.warehouse_id
                                            ? String(data.warehouse_id)
                                            : ''
                                    }
                                    onChange={(val) =>
                                        setData('warehouse_id', String(val))
                                    }
                                >
                                    <Select.Trigger className="mt-1">
                                        <Select.Value />
                                        <Select.Indicator />
                                    </Select.Trigger>
                                    <Select.Popover>
                                        <ListBox>
                                            {warehouses.map((wh) => (
                                                <ListBox.Item
                                                    key={wh.id}
                                                    id={String(wh.id)}
                                                    textValue={`${wh.code} — ${wh.name}`}
                                                >
                                                    {wh.code} — {wh.name}
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
                            <div className="md:col-span-2">
                                <Label className="block text-xs font-semibold text-foreground">
                                    Transfer retur dari cabang (opsional)
                                </Label>
                                <Select
                                    fullWidth
                                    placeholder="Tanpa transfer — pakai stok HO"
                                    value={
                                        data.return_transfer_id
                                            ? String(data.return_transfer_id)
                                            : ''
                                    }
                                    onChange={(val) => {
                                        setData(
                                            'return_transfer_id',
                                            val ? String(val) : '',
                                        );

                                        const params = new URLSearchParams(
                                            window.location.search,
                                        );

                                        if (val) {
                                            params.set(
                                                'returnTransfer',
                                                String(val),
                                            );
                                        } else {
                                            params.delete('returnTransfer');
                                        }

                                        // preserveState agar qty/gudang/pesan
                                        // yang sudah diisi tak hilang saat
                                        // availability terfilter dimuat ulang.
                                        router.get(
                                            `${window.location.pathname}?${params.toString()}`,
                                            {},
                                            {
                                                preserveState: true,
                                                preserveScroll: true,
                                                only: [
                                                    'availability',
                                                    'selectedTransferId',
                                                    'transfers',
                                                ],
                                            },
                                        );
                                    }}
                                >
                                    <Select.Trigger className="mt-1">
                                        <Select.Value />
                                        <Select.Indicator />
                                    </Select.Trigger>
                                    <Select.Popover>
                                        <ListBox>
                                            {transfers.map((trf) => (
                                                <ListBox.Item
                                                    key={trf.id}
                                                    id={String(trf.id)}
                                                    textValue={`${trf.number} — ${trf.from_warehouse_name}`}
                                                >
                                                    {trf.number} —{' '}
                                                    {trf.from_warehouse_name}
                                                </ListBox.Item>
                                            ))}
                                        </ListBox>
                                    </Select.Popover>
                                </Select>
                                {errors.return_transfer_id && (
                                    <p className="mt-1 text-xs text-danger">
                                        {errors.return_transfer_id}
                                    </p>
                                )}
                                <p className="mt-1 text-xs text-muted">
                                    Bila diisi, retur hanya memakai stok dari
                                    transfer itu; stok HO lain diabaikan.
                                </p>
                                {transfersTruncated && (
                                    <p className="mt-1 text-xs text-danger">
                                        Terlalu banyak transfer untuk
                                        ditampilkan. Cari transfer lewat menu
                                        Stock Transfer lalu ambil nomornya.
                                    </p>
                                )}
                            </div>
                            <div>
                                <TagComboBox
                                    tags={allTags}
                                    selectedTagIds={data.tag_ids}
                                    onSelectedTagsChange={(tagIds) =>
                                        setData('tag_ids', tagIds.map(Number))
                                    }
                                    onCreateTag={handleCreateTag}
                                    placeholder="Pilih atau cari tag"
                                />
                                {errors.tag_ids && (
                                    <p className="mt-1 text-xs text-danger">
                                        {errors.tag_ids as string}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div>
                            <h3 className="mb-3 text-sm font-bold text-foreground">
                                Quantity Retur
                            </h3>
                            <div className="overflow-hidden rounded-lg border border-border">
                                <table className="w-full text-left text-sm text-foreground">
                                    <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                        <tr>
                                            <th className="px-4 py-3">
                                                Produk
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Sisa
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Qty Retur
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Harga (DO, terkunci)
                                            </th>
                                            <th className="px-4 py-3 text-right">
                                                Jumlah
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border/60">
                                        {lines.map((line, index) => (
                                            <tr
                                                key={
                                                    line.ref
                                                        ?.purchase_invoice_item_id ??
                                                    index
                                                }
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-semibold">
                                                        {line.ref?.product_name}
                                                    </p>
                                                    <p className="font-mono text-xs text-muted">
                                                        {line.ref?.sku}
                                                    </p>
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium">
                                                    {line.ref?.remaining_qty}
                                                    {line.ref?.is_tracked && (
                                                        <p className="text-xs font-normal text-muted">
                                                            Stok gudang:{' '}
                                                            {warehouseAvailability[
                                                                String(
                                                                    line.ref
                                                                        ?.product_variant_id,
                                                                )
                                                            ] ?? 0}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        max={line.cap}
                                                        step="any"
                                                        value={String(
                                                            data.items[index]
                                                                ?.qty ?? 0,
                                                        )}
                                                        onChange={(e) => {
                                                            const next = [
                                                                ...data.items,
                                                            ];
                                                            next[index] = {
                                                                ...next[index],
                                                                qty: Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            };
                                                            setData(
                                                                'items',
                                                                next,
                                                            );
                                                        }}
                                                        className="ml-auto w-28 text-right"
                                                        aria-label={`Qty retur ${line.ref?.product_name ?? ''}`}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    {formatCurrency(
                                                        line.ref?.unit_price ??
                                                            0,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right font-bold">
                                                    {formatCurrency(
                                                        line.totals.lineTotal,
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            {errors.items && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.items as string}
                                </p>
                            )}
                            <p className="mt-2 text-xs text-muted">
                                Harga mengikuti faktur/DO dan tidak bisa diubah.
                                Nilai akhir dihitung ulang di server.
                            </p>
                        </div>

                        <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-2">
                            <div>
                                <Label className="block text-xs font-semibold text-foreground">
                                    Pesan (tercetak di formulir retur)
                                </Label>
                                <TextArea
                                    value={data.message}
                                    onChange={(e) =>
                                        setData('message', e.target.value)
                                    }
                                    placeholder="Keterangan tambahan untuk supplier..."
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <Label className="block text-xs font-semibold text-foreground">
                                    Memo (hanya tampil di laporan)
                                </Label>
                                <TextArea
                                    value={data.memo}
                                    onChange={(e) =>
                                        setData('memo', e.target.value)
                                    }
                                    placeholder="Catatan internal..."
                                    className="mt-1"
                                />
                            </div>
                        </div>

                        <div>
                            <Label className="block text-xs font-semibold text-foreground">
                                Lampiran (JPG/PNG/PDF, maks 5 MB per berkas,
                                maks 5)
                            </Label>
                            <input
                                type="file"
                                multiple
                                accept=".jpg,.jpeg,.png,.pdf"
                                onChange={(e) =>
                                    setData(
                                        'attachments',
                                        Array.from(e.target.files ?? []),
                                    )
                                }
                                className="mt-1 block w-full text-sm text-foreground file:mr-3 file:rounded-lg file:border file:border-border file:bg-surface-secondary file:px-3 file:py-1.5 file:text-sm file:font-semibold"
                            />
                            {errors.attachments && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.attachments as string}
                                </p>
                            )}
                        </div>

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
                                Buat Retur
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </CompanyLayout>
    );
}
