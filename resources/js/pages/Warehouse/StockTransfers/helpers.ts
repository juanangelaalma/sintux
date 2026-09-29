import type { StockTransferItem } from './types';

export const shippedQty = (item: StockTransferItem): number =>
    item.qty_shipped ?? item.qty;

export const remainingQty = (item: StockTransferItem): number =>
    shippedQty(item) - (item.qty_received ?? 0);

export function itemName(item: StockTransferItem): string {
    const name = item.product_variant?.product?.name;
    const variant = item.product_variant?.variant_name;

    if (!name) {
        return variant ?? 'Barang';
    }

    return variant ? `${name} - ${variant}` : name;
}

/** Nilai qty form selalu dalam rentang 0..max, termasuk saat kolom dikosongkan (parseInt -> NaN). */
export const clampQty = (value: number, max: number): number =>
    Math.max(0, Math.min(max, Number.isFinite(value) ? value : 0));

/** Langkah progres yang ditampilkan di stepper. Status di luar daftar ini tidak punya stepper. */
export const TRANSFER_STEPS = [
    { key: 'draft', label: 'Draft' },
    { key: 'shipped', label: 'Dikirim' },
    { key: 'received', label: 'Diterima' },
] as const;

export const NEXT_STEP_HINT: Record<string, string> = {
    pending_approval: 'Menunggu persetujuan sebelum bisa dikirim.',
    draft: 'Siap dikirim. Stok gudang asal akan berkurang saat dikirim.',
    shipped:
        'Barang dalam perjalanan. Gudang tujuan perlu konfirmasi penerimaan.',
    received:
        'Transfer selesai. Barang bisa diretur ke Head Office bila perlu.',
    rejected: 'Ditolak Head Office. Transfer ini tidak dapat dikirim.',
    cancelled: 'Dibatalkan. Transfer ini tidak lagi memakai stok gudang.',
};
