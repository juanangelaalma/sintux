<?php

namespace Modules\Warehouse\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Company\Access\CompanyAccess;
use Modules\Product\Models\ProductVariant;
use Modules\Warehouse\Application\StockAdjustment\CreateStockAdjustment;
use Modules\Warehouse\Application\StockAdjustment\GetStockAdjustmentDetail;
use Modules\Warehouse\Application\StockAdjustment\GetStockAdjustments;
use Modules\Warehouse\Application\StockAdjustment\PostStockAdjustment;
use Modules\Warehouse\Http\Requests\StoreStockAdjustmentRequest;
use Modules\Warehouse\Models\Warehouse;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private readonly GetStockAdjustments $getStockAdjustments,
        private readonly GetStockAdjustmentDetail $getStockAdjustmentDetail,
        private readonly CreateStockAdjustment $createStockAdjustment,
        private readonly PostStockAdjustment $postStockAdjustment,
    ) {}

    public function index()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'type', 'status', 'warehouse_id']);

        $adjustments = $this->getStockAdjustments->execute($accessibleBranchIds, $filters);
        $warehouses = Warehouse::whereIn('branch_id', $accessibleBranchIds)
            ->where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Warehouse/Adjustments/Index', [
            'adjustments' => $adjustments,
            'filters' => $filters,
            'warehouses' => $warehouses,
        ]);
    }

    public function create()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $warehouses = Warehouse::with('branch')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('is_active', true)
            ->get();

        $productVariants = ProductVariant::with('product')
            ->where('is_active', true)
            ->get();

        return Inertia::render('Warehouse/Adjustments/Create', [
            'warehouses' => $warehouses,
            'productVariants' => $productVariants,
        ]);
    }

    public function store(StoreStockAdjustmentRequest $request)
    {
        $adjustment = $this->createStockAdjustment->execute(
            $request->validated(),
            $request->user()->id,
        );

        return redirect()->route('warehouse.adjustments.show', $adjustment->id)
            ->with('success', 'Penyesuaian stok berhasil dibuat (Draft).');
    }

    public function show(int $id)
    {
        $adjustment = $this->getStockAdjustmentDetail->execute($id);

        return Inertia::render('Warehouse/Adjustments/Show', [
            'adjustment' => $adjustment,
        ]);
    }

    public function post(int $id)
    {
        $adjustment = $this->postStockAdjustment->execute($id, request()->user()->id);

        return redirect()->route('warehouse.adjustments.show', $adjustment->id)
            ->with('success', 'Penyesuaian stok berhasil diposting.');
    }

    private function resolveBranchIds($user, string $tenantId): array
    {
        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
