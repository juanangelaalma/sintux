<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Accounting\Application\Journal\GetJournalByReference;
use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Warehouse\Application\StockTransfer\GetStockTransferDetail;
use Modules\Warehouse\Application\Warehouse\GetWarehouse;

class GetPurchaseReturnDetail
{
    public function __construct(
        private readonly GetJournalByReference $journals,
        private readonly GetWarehouse $warehouses,
        private readonly GetStockTransferDetail $transfers,
    ) {}

    /**
     * Proyeksi halaman detail retur: header + baris + faktur sumber +
     * jurnal + debit memo + tag + lampiran.
     *
     * @param  list<int>  $branchIds  Scope cabang untuk nama gudang.
     * @return array<string, mixed>
     */
    public function execute(int $id, array $branchIds = []): array
    {
        $purchaseReturn = PurchaseReturn::with(['items', 'invoice', 'debitMemos', 'tags', 'attachments'])
            ->findOrFail($id);

        $warehouse = $branchIds === []
            ? null
            : $this->warehouses->execute((int) $purchaseReturn->warehouse_id, $branchIds);

        $transfer = $purchaseReturn->return_transfer_id !== null
            ? $this->transferSummary((int) $purchaseReturn->return_transfer_id)
            : null;

        return [
            'return' => [
                'id' => (int) $purchaseReturn->id,
                'number' => (string) $purchaseReturn->number,
                'status' => (string) $purchaseReturn->status,
                'return_date' => $purchaseReturn->return_date->toDateString(),
                'message' => $purchaseReturn->message,
                'memo' => $purchaseReturn->memo,
                'is_tax_inclusive' => (bool) $purchaseReturn->is_tax_inclusive,
                'subtotal' => (float) $purchaseReturn->subtotal,
                'tax_amount' => (float) $purchaseReturn->tax_amount,
                'total' => (float) $purchaseReturn->total,
                'supplier_id' => (int) $purchaseReturn->supplier_id,
                'warehouse' => [
                    'id' => (int) $purchaseReturn->warehouse_id,
                    'name' => (string) ($warehouse?->name ?? ''),
                ],
            ],
            'invoice' => [
                'id' => (int) $purchaseReturn->purchase_invoice_id,
                'number' => (string) ($purchaseReturn->invoice?->number ?? ''),
                'status' => $purchaseReturn->invoice?->status instanceof \BackedEnum
                    ? $purchaseReturn->invoice->status->value
                    : (string) ($purchaseReturn->invoice?->status ?? ''),
            ],
            'items' => $purchaseReturn->items->map(fn ($item): array => [
                'id' => (int) $item->id,
                'product_name' => (string) $item->product_name,
                'sku' => (string) $item->sku,
                'uom_name' => $item->uom_name,
                'qty' => (float) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'tax_rate' => (float) $item->tax_rate,
                'line_total' => (float) $item->line_total,
            ])->all(),
            'transfer' => $transfer,
            'journal' => $this->journals->execute('purchase_return', $purchaseReturn->id),
            'debitMemos' => $purchaseReturn->debitMemos->map(fn ($memo): array => [
                'id' => (int) $memo->id,
                'number' => (string) $memo->number,
                'status' => (string) $memo->status,
                'total' => (float) $memo->total,
                'remaining' => (float) $memo->remaining,
            ])->all(),
            'tags' => $purchaseReturn->tags->map(fn ($tag): array => [
                'id' => (int) $tag->id,
                'name' => (string) $tag->name,
            ])->all(),
            'attachments' => $purchaseReturn->attachments->map(fn ($file): array => [
                'id' => (int) $file->id,
                'original_name' => (string) ($file->original_name ?? ''),
                'mime' => $file->mime,
                'size' => $file->size !== null ? (int) $file->size : null,
            ])->all(),
        ];
    }

    /**
     * @return array{id: int, number: string, status: string, from_warehouse_name: string}|null
     */
    private function transferSummary(int $transferId): ?array
    {
        try {
            $transfer = $this->transfers->execute($transferId);
        } catch (ModelNotFoundException $e) {
            return null;
        }

        return [
            'id' => (int) $transfer->id,
            'number' => (string) ($transfer->number ?? ('#'.$transfer->id)),
            'status' => (string) $transfer->status,
            'from_warehouse_name' => (string) ($transfer->fromWarehouse?->name ?? ''),
        ];
    }
}
