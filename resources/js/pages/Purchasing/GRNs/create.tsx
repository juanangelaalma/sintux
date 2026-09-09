import { Button } from '@heroui/react';
import { Head, Link, useForm } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { useMemo, useState } from 'react';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatCurrency } from '@/lib/format';

type Warehouse = { id: number; code: string; name: string };

type POItem = {
    id: number;
    product_variant_id: number;
    product_name: string;
    sku: string;
    qty: number;
    qty_ordered: number;
    qty_received: number;
    unit_price: number;
    line_total: number;
    destination_branch_id: number;
    destination_branch_code?: string | null;
    destination_branch_name?: string | null;
};

type OrderOption = {
    id: number;
    number: string;
    branch_id: number;
    supplier_id: number;
    status: string;
    status_label: string;
    note?: string;
    items: POItem[];
};

type GRNItemRow = {
    purchase_order_item_id: number;
    product_variant_id: number;
    product_name: string;
    sku: string;
    qty_received: number;
    unit_price: number;
    destination_branch_id: number;
    destination_branch_code?: string | null;
    destination_branch_name?: string | null;
};

type BranchItemGroup = {
    key: string;
    branchCode: string;
    branchName: string;
    rows: { item: GRNItemRow; index: number }[];
    totalQty: number;
};

type Props = {
    warehouses: Warehouse[];
    purchaseOrders: OrderOption[];
};

export default function GoodsReceiptsCreate({
    warehouses,
    purchaseOrders,
}: Props) {
    const [selectedPoId, setSelectedPoId] = useState<number | string>(
        purchaseOrders[0]?.id ?? '',
    );

    const { data, setData, post, processing, errors } = useForm<{
        branch_id: number | string;
        supplier_id: number | string;
        purchase_order_id: number | string;
        warehouse_id: number | string;
        receipt_date: string;
        note: string;
        items: GRNItemRow[];
    }>({
        branch_id: purchaseOrders[0]?.branch_id ?? '',
        supplier_id: purchaseOrders[0]?.supplier_id ?? '',
        purchase_order_id: purchaseOrders[0]?.id ?? '',
        warehouse_id: warehouses[0]?.id ?? '',
        receipt_date: new Date().toISOString().split('T')[0],
        note: '',
        items: [],
    });

    const selectedPo = purchaseOrders.find(
        (po) => po.id === Number(selectedPoId),
    );

    const handleSelectPO = (poId: number | string) => {
        const po = purchaseOrders.find((p) => p.id === Number(poId));

        if (!po) {
            setSelectedPoId('');
            setData({
                ...data,
                branch_id: '',
                supplier_id: '',
                purchase_order_id: '',
                items: [],
            });

            return;
        }

        setSelectedPoId(po.id);
        setData({
            ...data,
            branch_id: po.branch_id,
            supplier_id: po.supplier_id,
            purchase_order_id: po.id,
            note: po.note || data.note,
            items: po.items
                .filter(
                    (item) =>
                        Math.max(0, item.qty_ordered - item.qty_received) > 0,
                )
                .map((item) => ({
                    purchase_order_item_id: item.id,
                    product_variant_id: item.product_variant_id,
                    product_name: item.product_name,
                    sku: item.sku,
                    qty_received: Math.max(
                        0,
                        item.qty_ordered - item.qty_received,
                    ),
                    unit_price: item.unit_price,
                    destination_branch_id: item.destination_branch_id,
                    destination_branch_code: item.destination_branch_code,
                    destination_branch_name: item.destination_branch_name,
                })),
        });
    };

    const updateQty = (index: number, qty: number) => {
        const next = [...data.items];
        next[index] = { ...next[index], qty_received: qty };
        setData('items', next);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/purchasing/grns');
    };

    const totalReceived = data.items.reduce(
        (sum, item) => sum + Number(item.qty_received || 0),
        0,
    );

    const errorEntries = Object.entries(errors);
    const hasErrors = errorEntries.length > 0;
    const noReceivableItems =
        selectedPo !== undefined && data.items.length === 0;

    const rowErrors = (index: number): string[] =>
        errorEntries
            .filter(([key]) => key.startsWith(`items.${index}.`))
            .map(([, message]) => String(message));

    const branchGroups = useMemo<BranchItemGroup[]>(() => {
        const map = new Map<string, BranchItemGroup>();

        data.items.forEach((item, index) => {
            const key = String(item.destination_branch_id || 'unassigned');
            const existing = map.get(key);
            const code =
                item.destination_branch_code ||
                (item.destination_branch_id
                    ? `BR-${item.destination_branch_id}`
                    : 'HQ');
            const name =
                item.destination_branch_name ||
                (item.destination_branch_id
                    ? `Cabang ${item.destination_branch_id}`
                    : 'Pusat');
            const qty = Number(item.qty_received || 0);

            if (existing) {
                existing.rows.push({ item, index });
                existing.totalQty += qty;
            } else {
                map.set(key, {
                    key,
                    branchCode: code,
                    branchName: name,
                    rows: [{ item, index }],
                    totalQty: qty,
                });
            }
        });

        return Array.from(map.values());
    }, [data.items]);

    return (
        <CompanyLayout>
            <Head title="Buat Penerimaan Barang" />
            <div className="w-full space-y-6">
                <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                            Pembelian
                        </p>
                        <h1 className="mt-1 text-2xl font-bold text-foreground">
                            Buat Penerimaan Barang (Goods Receipt)
                        </h1>
                    </div>
                </div>

                <form
                    onSubmit={handleSubmit}
                    className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs"
                >
                    {hasErrors && (
                        <div
                            role="alert"
                            className="rounded-lg border border-danger/40 bg-danger/5 p-4"
                        >
                            <p className="text-sm font-bold text-danger">
                                Penerimaan tidak dapat disimpan. Periksa kembali
                                isian berikut:
                            </p>
                            <ul className="mt-2 list-disc space-y-1 pl-5 text-xs text-danger">
                                {errorEntries.map(([key, message]) => (
                                    <li key={key}>{String(message)}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                    <div className="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Pesanan Pembelian (PO) *
                            </label>
                            <select
                                value={selectedPoId}
                                onChange={(e) => handleSelectPO(e.target.value)}
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            >
                                <option value="">Pilih PO</option>
                                {purchaseOrders.map((po) => (
                                    <option key={po.id} value={po.id}>
                                        PO #{po.number} — {po.status_label}
                                    </option>
                                ))}
                            </select>
                            {errors.purchase_order_id && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.purchase_order_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Gudang HQ Penerima (Regular)
                            </label>
                            <div className="mt-1 block w-full rounded-lg border border-border bg-surface-secondary/40 px-3 py-2 text-sm text-foreground">
                                {warehouses[0]
                                    ? `${warehouses[0].code} — ${warehouses[0].name}`
                                    : 'GD-HQ-REG belum tersedia'}
                            </div>
                            {errors.warehouse_id && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.warehouse_id}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="block text-xs font-semibold text-foreground">
                                Tanggal Penerimaan *
                            </label>
                            <input
                                type="date"
                                value={data.receipt_date}
                                onChange={(e) =>
                                    setData('receipt_date', e.target.value)
                                }
                                className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                            />
                            {errors.receipt_date && (
                                <p className="mt-1 text-xs text-danger">
                                    {errors.receipt_date}
                                </p>
                            )}
                        </div>
                    </div>

                    {selectedPo && (
                        <div className="rounded-lg border border-border bg-surface-secondary p-4">
                            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h3 className="text-sm font-bold text-foreground">
                                    Item Diterima — PO #{selectedPo.number}
                                </h3>
                                <span className="text-xs font-medium text-muted">
                                    Total qty: {totalReceived}
                                </span>
                            </div>

                            {data.items.length === 0 ? (
                                <p className="text-sm text-muted">
                                    {selectedPo
                                        ? 'Semua item PO ini sudah diterima penuh.'
                                        : 'Pilih PO untuk memuat daftar item.'}
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    {branchGroups.map((group) => (
                                        <div
                                            key={group.key}
                                            className="overflow-hidden rounded-xl border border-border bg-surface shadow-2xs"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-border bg-surface-secondary/40 px-4 py-2.5">
                                                <div className="flex items-center gap-2">
                                                    <div
                                                        className="flex size-7 items-center justify-center rounded-md bg-accent/10 text-accent"
                                                        aria-hidden
                                                    >
                                                        <Building2 className="size-3.5" />
                                                    </div>
                                                    <span className="text-xs font-bold text-foreground">
                                                        Alokasi Cabang Tujuan:{' '}
                                                        {group.branchCode} —{' '}
                                                        {group.branchName}
                                                    </span>
                                                </div>
                                                <span className="text-xs font-medium text-muted">
                                                    {group.rows.length} item •
                                                    Total diterima:{' '}
                                                    <span className="font-bold text-foreground">
                                                        {group.totalQty}
                                                    </span>
                                                </span>
                                            </div>

                                            <div className="overflow-x-auto">
                                                <table className="w-full text-left text-sm text-foreground">
                                                    <thead className="border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200">
                                                        <tr>
                                                            <th className="px-4 py-3">
                                                                Produk
                                                            </th>
                                                            <th className="px-4 py-3">
                                                                SKU
                                                            </th>
                                                            <th className="px-4 py-3 text-right">
                                                                Pesanan
                                                            </th>
                                                            <th className="px-4 py-3 text-right">
                                                                Sudah Diterima
                                                            </th>
                                                            <th className="px-4 py-3 text-right">
                                                                Harga Satuan
                                                            </th>
                                                            <th className="px-4 py-3 text-right">
                                                                Terima Sekarang
                                                                *
                                                            </th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-border/60">
                                                        {group.rows.map(
                                                            ({
                                                                item,
                                                                index,
                                                            }) => {
                                                                const poItem =
                                                                    selectedPo.items.find(
                                                                        (i) =>
                                                                            i.id ===
                                                                            item.purchase_order_item_id,
                                                                    );
                                                                const maxQty =
                                                                    poItem
                                                                        ? Math.max(
                                                                              0,
                                                                              poItem.qty_ordered -
                                                                                  poItem.qty_received,
                                                                          )
                                                                        : 0;
                                                                const itemErrors =
                                                                    rowErrors(
                                                                        index,
                                                                    );

                                                                return (
                                                                    <tr
                                                                        key={
                                                                            item.purchase_order_item_id
                                                                        }
                                                                        className="hover:bg-surface-secondary/60"
                                                                    >
                                                                        <td className="px-4 py-3 font-semibold text-foreground">
                                                                            {
                                                                                item.product_name
                                                                            }
                                                                            {itemErrors.length >
                                                                                0 && (
                                                                                <ul className="mt-1 space-y-0.5 text-xs font-normal text-danger">
                                                                                    {itemErrors.map(
                                                                                        (
                                                                                            message,
                                                                                            i,
                                                                                        ) => (
                                                                                            <li
                                                                                                key={`${index}-${i}`}
                                                                                            >
                                                                                                {
                                                                                                    message
                                                                                                }
                                                                                            </li>
                                                                                        ),
                                                                                    )}
                                                                                </ul>
                                                                            )}
                                                                        </td>
                                                                        <td className="px-4 py-3 font-mono text-xs text-muted">
                                                                            {
                                                                                item.sku
                                                                            }
                                                                        </td>
                                                                        <td className="px-4 py-3 text-right font-medium">
                                                                            {poItem?.qty_ordered ??
                                                                                0}
                                                                        </td>
                                                                        <td className="px-4 py-3 text-right font-medium text-muted">
                                                                            {poItem?.qty_received ??
                                                                                0}
                                                                        </td>
                                                                        <td className="px-4 py-3 text-right">
                                                                            {formatCurrency(
                                                                                item.unit_price,
                                                                            )}
                                                                        </td>
                                                                        <td className="px-4 py-3 text-right">
                                                                            <input
                                                                                type="number"
                                                                                min={
                                                                                    0
                                                                                }
                                                                                max={
                                                                                    maxQty ||
                                                                                    undefined
                                                                                }
                                                                                step="any"
                                                                                value={
                                                                                    item.qty_received
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updateQty(
                                                                                        index,
                                                                                        Number(
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        ),
                                                                                    )
                                                                                }
                                                                                className="ml-auto block w-28 rounded-lg border-border bg-surface text-right text-sm text-foreground focus:border-accent focus:ring-accent"
                                                                            />
                                                                        </td>
                                                                    </tr>
                                                                );
                                                            },
                                                        )}
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {errors.items && (
                                <p className="mt-2 text-xs text-danger">
                                    {errors.items}
                                </p>
                            )}
                        </div>
                    )}

                    <div>
                        <label className="block text-xs font-semibold text-foreground">
                            Catatan Penerimaan
                        </label>
                        <textarea
                            rows={3}
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            placeholder="Catatan kondisi fisik barang atau surat jalan supplier"
                            className="mt-1 block w-full rounded-lg border-border bg-surface text-sm text-foreground focus:border-accent focus:ring-accent"
                        />
                    </div>

                    <div className="flex justify-end gap-3 border-t border-border/60 pt-4">
                        <Link href="/purchasing/grns">
                            <Button type="button" variant="secondary">
                                Batal
                            </Button>
                        </Link>
                        <Button
                            type="submit"
                            variant="primary"
                            isDisabled={processing || noReceivableItems}
                        >
                            Simpan Penerimaan Barang
                        </Button>
                    </div>
                </form>
            </div>
        </CompanyLayout>
    );
}
