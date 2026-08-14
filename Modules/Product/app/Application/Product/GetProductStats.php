<?php

namespace Modules\Product\Application\Product;

use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockRequest;
use Modules\Warehouse\Models\Warehouse;

class GetProductStats
{
    /**
     * Get aggregate statistics for Product Hub Dashboard Cards.
     *
     * @param  list<int>  $accessibleBranchIds
     * @return array{
     *     available_count: int,
     *     low_stock_count: int,
     *     out_of_stock_count: int,
     *     warehouses_count: int,
     *     pending_requests_count: int
     * }
     */
    public function execute(array $accessibleBranchIds): array
    {
        $totalProducts = Product::where('is_active', true)->count();

        // Calculate aggregated stock across accessible branch warehouses
        $warehouseIds = Warehouse::whereIn('branch_id', $accessibleBranchIds)
            ->pluck('id');

        $stockStats = StockBalance::whereIn('warehouse_id', $warehouseIds)
            ->selectRaw('
                COUNT(CASE WHEN qty_on_hand > 5 THEN 1 END) as available_count,
                COUNT(CASE WHEN qty_on_hand > 0 AND qty_on_hand <= 5 THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN qty_on_hand = 0 THEN 1 END) as out_of_stock_count
            ')
            ->first();

        $availableCount = (int) ($stockStats->available_count ?? $totalProducts);
        $lowStockCount = (int) ($stockStats->low_stock_count ?? 0);
        $outOfStockCount = (int) ($stockStats->out_of_stock_count ?? 0);

        $warehousesCount = Warehouse::whereIn('branch_id', $accessibleBranchIds)->count();

        $pendingRequestsCount = StockRequest::whereHas('requestingWarehouse', function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branch_id', $accessibleBranchIds);
        })->where('status', 'pending')->count();

        return [
            'available_count' => $availableCount,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'warehouses_count' => $warehousesCount,
            'pending_requests_count' => $pendingRequestsCount,
        ];
    }
}
