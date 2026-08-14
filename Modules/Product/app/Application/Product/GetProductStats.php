<?php

namespace Modules\Product\Application\Product;

use Modules\Warehouse\Application\StockBalance\GetStockBalances;
use Modules\Warehouse\Application\StockRequest\GetStockRequests;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class GetProductStats
{
    public function __construct(
        private readonly GetWarehouses $getWarehouses,
        private readonly GetStockBalances $getStockBalances,
        private readonly GetStockRequests $getStockRequests,
    ) {}

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
        $warehouseIds = $this->getWarehouses->warehouseIdsByBranches($accessibleBranchIds);

        $threshold = (int) config('product.low_stock_threshold', 5);
        $stockCounts = $this->getStockBalances->aggregateCountsByWarehouseIds($warehouseIds, $threshold);

        return [
            'available_count' => $stockCounts['available_count'],
            'low_stock_count' => $stockCounts['low_stock_count'],
            'out_of_stock_count' => $stockCounts['out_of_stock_count'],
            'warehouses_count' => $this->getWarehouses->countByBranchIds($accessibleBranchIds),
            'pending_requests_count' => $this->getStockRequests->pendingCountByBranchIds($accessibleBranchIds),
        ];
    }
}
