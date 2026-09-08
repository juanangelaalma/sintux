<?php

namespace Modules\Warehouse\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Application\StockLayer\GetStockLayers;
use Modules\Warehouse\Application\StockMovement\GetStockMovements;

class StockMovementController extends Controller
{
    public function __construct(
        private readonly GetStockMovements $getStockMovements,
        private readonly GetStockLayers $getStockLayers,
    ) {}

    public function index()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = CompanyAccess::contextBranchIds($user, $tenantId);

        $filters = request()->only([
            'search',
            'warehouse_id',
            'product_variant_id',
            'movement_type',
            'date_from',
            'date_to',
        ]);

        $stockMovements = $this->getStockMovements->execute(
            $accessibleBranchIds,
            $filters
        );

        if (request()->wantsJson()) {
            return response()->json($stockMovements);
        }

        return Inertia::render('Warehouse/StockMovements/Index', [
            'stockMovements' => $stockMovements,
            'filters' => $filters,
        ]);
    }

    public function layers(int $warehouseId, int $productVariantId)
    {
        $layers = $this->getStockLayers->execute($warehouseId, $productVariantId);

        return response()->json([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $productVariantId,
            'layers' => $layers,
        ]);
    }
}
