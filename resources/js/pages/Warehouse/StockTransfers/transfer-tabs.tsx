import { Tabs } from '@heroui/react';
import { Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { useFontsReady } from '@/hooks/use-fonts-ready';
import { formatCurrency, formatDate, formatQty } from '@/lib/format';
import { itemName, remainingQty, shippedQty } from './helpers';
import StockTransferStatusBadge from './status-badge';
import type {
    PurchaseLineage,
    ReturnTransfer,
    StockTransfer,
    StockTransferItem,
} from './types';

type TabKey = 'items' | 'returns' | 'lineage';

const TABLE_HEAD_CLASS =
    'border-b border-border bg-cyan-500/10 text-xs font-bold text-cyan-950 uppercase dark:bg-cyan-950/40 dark:text-cyan-200';

function EmptyState({ children }: { children: ReactNode }) {
    return (
        <p
            role="status"
            className="rounded-lg border border-dashed border-border p-6 text-center text-sm text-muted"
        >
            {children}
        </p>
    );
}

export default function TransferTabs({
    transfer,
    lineage,
}: {
    transfer: StockTransfer;
    lineage: PurchaseLineage | null;
}) {
    const fontsReady = useFontsReady();
    const [tab, setTab] = useState<TabKey>('items');
    const returns = transfer.return_transfers ?? [];
    const items = transfer.items ?? [];

    const tabs: { id: TabKey; label: string; count?: number }[] = [
        { id: 'items', label: 'Rincian Barang', count: items.length },
        { id: 'returns', label: 'Retur (RTRF)', count: returns.length },
        { id: 'lineage', label: 'Asal Pembelian' },
    ];

    return (
        <Tabs
            className="w-full"
            selectedKey={tab}
            onSelectionChange={(key) => setTab(key as TabKey)}
        >
            <Tabs.ListContainer>
                <Tabs.List aria-label="Bagian detail transfer stok">
                    {tabs.map((entry) => (
                        <Tabs.Tab key={entry.id} id={entry.id}>
                            <span className="flex items-center gap-1.5">
                                {entry.label}
                                {!!entry.count && (
                                    <span className="rounded-full bg-surface-secondary px-1.5 text-xs font-semibold text-muted">
                                        {entry.count}
                                    </span>
                                )}
                            </span>
                            {fontsReady && <Tabs.Indicator />}
                        </Tabs.Tab>
                    ))}
                </Tabs.List>
            </Tabs.ListContainer>

            <Tabs.Panel id="items" className="space-y-4">
                <ItemsPanel items={items} />
            </Tabs.Panel>
            <Tabs.Panel id="returns" className="space-y-4">
                <ReturnsPanel returns={returns} />
            </Tabs.Panel>
            <Tabs.Panel id="lineage" className="space-y-4">
                <LineagePanel lineage={lineage} items={items} />
            </Tabs.Panel>
        </Tabs>
    );
}

/* ---------- Rincian barang ---------- */

function ReceiveProgress({ item }: { item: StockTransferItem }) {
    const shipped = shippedQty(item);
    const received = item.qty_received ?? 0;
    const percent = shipped > 0 ? Math.min(100, (received / shipped) * 100) : 0;

    return (
        <div className="flex w-32 flex-col items-end gap-1">
            <span>
                {formatQty(received)}
                <span className="text-muted"> / {formatQty(shipped)}</span>
            </span>
            <div
                role="progressbar"
                aria-label={`Progres penerimaan ${itemName(item)}`}
                aria-valuenow={Math.round(percent)}
                aria-valuemin={0}
                aria-valuemax={100}
                className="h-1.5 w-full overflow-hidden rounded-full bg-surface-secondary"
            >
                <div
                    className="h-full rounded-full bg-accent"
                    style={{ width: `${percent}%` }}
                />
            </div>
        </div>
    );
}

function ItemsPanel({ items }: { items: StockTransferItem[] }) {
    if (items.length === 0) {
        return <EmptyState>Transfer ini belum punya item barang.</EmptyState>;
    }

    const withDetail = items.filter(
        (item) =>
            (item.layers?.length ?? 0) > 0 ||
            (item.discrepancies?.length ?? 0) > 0,
    );

    return (
        <>
            <div className="overflow-hidden rounded-lg border border-border">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[720px] text-left text-sm text-foreground">
                        <thead className={TABLE_HEAD_CLASS}>
                            <tr>
                                <th className="px-4 py-3">Produk</th>
                                <th className="px-4 py-3 text-right">
                                    Diminta
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Dikirim
                                </th>
                                <th className="px-4 py-3 text-right">
                                    Diterima
                                </th>
                                <th className="px-4 py-3 text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border/60">
                            {items.map((item) => (
                                <tr
                                    key={item.id}
                                    className="hover:bg-surface-secondary/60"
                                >
                                    <td className="px-4 py-3">
                                        <span className="font-semibold text-foreground">
                                            {itemName(item)}
                                        </span>
                                        <span className="ml-2 font-mono text-xs text-muted">
                                            {item.product_variant?.sku}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-right font-medium">
                                        {formatQty(item.qty)}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        {item.qty_shipped == null ? (
                                            <span className="text-muted">
                                                Belum dikirim
                                            </span>
                                        ) : (
                                            formatQty(item.qty_shipped)
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.qty_shipped == null ? (
                                            <span className="block text-right text-muted">
                                                -
                                            </span>
                                        ) : (
                                            <ReceiveProgress item={item} />
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right font-bold text-foreground">
                                        {formatQty(remainingQty(item))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            {withDetail.length > 0 && (
                <details className="rounded-lg border border-border" open>
                    <summary className="cursor-pointer px-4 py-3 text-sm font-bold text-foreground">
                        Rincian FIFO Costing dan Selisih Terima (
                        {withDetail.length})
                    </summary>
                    <div className="space-y-3 border-t border-border p-4">
                        {withDetail.map((item) => (
                            <div key={item.id} className="space-y-2">
                                <p className="text-sm font-semibold text-foreground">
                                    {itemName(item)}
                                    <span className="ml-2 font-mono text-xs font-normal text-muted">
                                        {item.product_variant?.sku}
                                    </span>
                                </p>
                                <ul className="space-y-1.5">
                                    {(item.layers ?? []).map((layer) => (
                                        <li
                                            key={layer.id}
                                            className="flex flex-wrap justify-between gap-2 rounded border border-border bg-surface-secondary/40 px-2.5 py-1.5 text-xs text-muted"
                                        >
                                            <span className="font-mono">
                                                Layer #{layer.stock_layer_id} ·{' '}
                                                {formatCurrency(
                                                    layer.unit_cost,
                                                )}
                                            </span>
                                            <span className="font-semibold text-foreground">
                                                Qty diambil:{' '}
                                                {formatQty(layer.qty_taken)}
                                            </span>
                                        </li>
                                    ))}
                                    {(item.discrepancies ?? []).map((gap) => (
                                        <li
                                            key={gap.id}
                                            className="flex flex-wrap justify-between gap-2 rounded border border-warning/30 bg-warning-soft px-2.5 py-1.5 text-xs text-warning"
                                        >
                                            <span>
                                                Dikirim{' '}
                                                {formatQty(gap.shipped_qty)} ·
                                                diterima{' '}
                                                {formatQty(gap.received_qty)} ·
                                                selisih{' '}
                                                {formatQty(gap.difference_qty)}
                                            </span>
                                            <span className="font-semibold">
                                                {gap.status}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </details>
            )}
        </>
    );
}

/* ---------- Retur (RTRF) ---------- */

function ReturnsPanel({ returns }: { returns: ReturnTransfer[] }) {
    return (
        <>
            <p className="text-sm text-muted">
                Retur transfer stok (RTRF) dibuat dari transfer ini ketika
                cabang mengirim barang kembali ke Head Office.
            </p>

            {returns.length === 0 ? (
                <EmptyState>
                    Belum ada retur untuk transfer ini. Retur hanya bisa dibuat
                    dari transfer HO ke cabang yang sudah berstatus diterima.
                </EmptyState>
            ) : (
                <div className="overflow-hidden rounded-lg border border-border">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[860px] text-left text-sm text-foreground">
                            <thead className={TABLE_HEAD_CLASS}>
                                <tr>
                                    <th className="px-4 py-3">Nomor RTRF</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="px-4 py-3">Asal → Tujuan</th>
                                    <th className="px-4 py-3 text-right">
                                        Total Qty
                                    </th>
                                    <th className="px-4 py-3">Dibuat</th>
                                    <th className="px-4 py-3">
                                        Diterima di HO
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border/60">
                                {returns.map((rtrf) => (
                                    <ReturnTransferRow
                                        key={rtrf.id}
                                        rtrf={rtrf}
                                    />
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            )}
        </>
    );
}

function ReturnTransferRow({ rtrf }: { rtrf: ReturnTransfer }) {
    const total = (rtrf.items ?? []).reduce(
        (sum, item) => sum + Number(item.qty ?? 0),
        0,
    );

    return (
        <tr className="hover:bg-surface-secondary/60">
            <td className="px-4 py-3">
                <Link
                    href={`/warehouse/stock-transfers/${rtrf.id}`}
                    className="font-semibold text-accent hover:underline"
                >
                    {rtrf.number ?? `#${rtrf.id}`}
                </Link>
            </td>
            <td className="px-4 py-3">
                <StockTransferStatusBadge status={rtrf.status} />
            </td>
            <td className="px-4 py-3 text-muted">
                {rtrf.from_warehouse?.name ?? '-'} →{' '}
                {rtrf.to_warehouse?.name ?? '-'}
            </td>
            <td className="px-4 py-3 text-right font-medium">
                {formatQty(total)}
            </td>
            <td className="px-4 py-3 text-muted">
                {formatDate(rtrf.created_at)}
            </td>
            <td className="px-4 py-3 text-muted">
                {rtrf.received_at ? formatDate(rtrf.received_at) : '-'}
            </td>
        </tr>
    );
}

/* ---------- Asal pembelian ---------- */

function LineagePanel({
    lineage,
    items,
}: {
    lineage: PurchaseLineage | null;
    items: StockTransferItem[];
}) {
    if (!lineage) {
        return (
            <EmptyState>
                Data asal pembelian tidak tersedia untuk transfer ini.
            </EmptyState>
        );
    }

    return (
        <>
            {lineage.invoice ? (
                <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-success/30 bg-success-soft px-3 py-2 text-sm text-success">
                    <div>
                        <span className="font-semibold">
                            {lineage.invoice.number}
                        </span>
                        <span className="ml-2 text-xs">
                            {lineage.invoice.status}
                        </span>
                    </div>
                    <Link
                        href={`/purchasing/invoices/${lineage.invoice.id}`}
                        className="text-xs font-semibold text-accent hover:underline"
                    >
                        Lihat faktur
                    </Link>
                </div>
            ) : (
                <p className="rounded-lg border border-border bg-surface-secondary/40 p-3 text-sm text-muted">
                    Transfer ini tidak membawa referensi penerimaan barang, jadi
                    faktur asalnya tidak bisa ditentukan.
                </p>
            )}

            {lineage.items.map((lineageItem) => {
                const item = items.find(
                    (candidate) =>
                        candidate.id === lineageItem.stock_transfer_item_id,
                );

                return (
                    <div
                        key={lineageItem.stock_transfer_item_id}
                        className="rounded-lg border border-border p-4"
                    >
                        <p className="text-sm font-semibold text-foreground">
                            {item ? itemName(item) : 'Barang'}
                        </p>

                        {lineageItem.origins.length === 0 ? (
                            <p className="mt-1 text-xs text-muted">
                                Belum ada layer asal, transfer belum dikirim.
                            </p>
                        ) : (
                            <ul className="mt-2 space-y-1.5">
                                {lineageItem.origins.map((origin) => (
                                    <li
                                        key={origin.layer_id}
                                        className="flex flex-wrap justify-between gap-2 rounded border border-border bg-surface-secondary/40 px-2.5 py-1.5 text-xs text-muted"
                                    >
                                        <span>
                                            Layer #{origin.layer_id} ·{' '}
                                            {origin.warehouse_name} · akar:{' '}
                                            {origin.root_source_type}#
                                            {origin.root_source_id ?? '-'}
                                        </span>
                                        <span className="font-semibold text-foreground">
                                            Qty: {formatQty(origin.qty_taken)} ·{' '}
                                            {formatCurrency(origin.unit_cost)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                );
            })}
        </>
    );
}
