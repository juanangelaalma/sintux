<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\PurchaseOrder;

class GetReceivablePurchaseOrders
{
    /**
     * PO terkirim yang punya alokasi untuk cabang (dropdown form fetch).
     *
     * @return list<array{id: int, number: string}>
     */
    public function execute(int $branchId): array
    {
        return PurchaseOrder::query()
            ->whereIn('status', [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived])
            ->whereHas('items', fn ($q) => $q->where('destination_branch_id', $branchId))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'number'])
            ->map(fn ($po) => ['id' => (int) $po->id, 'number' => $po->number])
            ->all();
    }
}
