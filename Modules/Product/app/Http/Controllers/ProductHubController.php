<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Application\Category\GetCategories;
use Modules\Product\Application\Product\GetProducts;
use Modules\Product\Application\Product\GetProductStats;
use Modules\Product\Application\Uom\GetUoms;
use Modules\Warehouse\Application\StockAdjustment\GetStockAdjustments;
use Modules\Warehouse\Application\StockBalance\GetStockBalances;
use Modules\Warehouse\Application\StockRequest\GetStockRequests;
use Modules\Warehouse\Application\StockTransfer\GetStockTransfers;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class ProductHubController extends Controller
{
    public function __construct(
        private readonly GetProductStats $getProductStats,
        private readonly GetProducts $getProducts,
        private readonly GetCategories $getCategories,
        private readonly GetUoms $getUoms,
        private readonly GetWarehouses $getWarehouses,
        private readonly GetStockBalances $getStockBalances,
        private readonly GetStockRequests $getStockRequests,
        private readonly GetStockAdjustments $getStockAdjustments,
        private readonly GetStockTransfers $getStockTransfers,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId);

        $activeTab = request('tab', 'items'); // 'items' | 'gudang' | 'master'
        $subTab = request('sub', 'products'); // 'products' | 'warehouses' | 'balances' | 'requests' | 'adjustments' | 'transfers' | 'categories' | 'uoms'

        $stats = $this->getProductStats->execute($branchIds);
        $filters = request()->only(['search', 'category_id', 'product_type', 'status', 'warehouse_id', 'type']);

        // Muat dataset seperlunya per tab aktif: pindah tab tidak ikut
        // menjalankan query tab lain yang tidak ditampilkan.
        $props = [
            'stats' => $stats,
            'activeTab' => $activeTab,
            'subTab' => $subTab,
            'filters' => $filters,
            'categories' => $this->getCategories->all(),
            'uoms' => $this->getUoms->all(),
        ];

        if ($activeTab === 'items') {
            $props['products'] = $this->getProducts->execute($filters, $branchIds);
        }

        if ($activeTab === 'gudang') {
            $props['warehouses'] = $this->getWarehouses->all($branchIds);
            $props['stockBalances'] = $this->getStockBalances->execute($branchIds, $filters);
            $props['stockRequests'] = $this->getStockRequests->execute($branchIds, $filters);
            $props['stockAdjustments'] = $this->getStockAdjustments->execute($branchIds, $filters);
            $props['stockTransfers'] = $this->getStockTransfers->execute($branchIds, $filters);
        }

        return Inertia::render('Product/Index', $props);
    }
}
