<?php

namespace Modules\Warehouse\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Models\ProductVariant;
use Modules\Warehouse\Application\StockRequest\ApproveStockRequest;
use Modules\Warehouse\Application\StockRequest\CreateStockRequest;
use Modules\Warehouse\Application\StockRequest\GetStockRequestDetail;
use Modules\Warehouse\Application\StockRequest\GetStockRequests;
use Modules\Warehouse\Http\Requests\ApproveStockRequestRequest;
use Modules\Warehouse\Http\Requests\StoreStockRequestRequest;
use Modules\Warehouse\Models\Warehouse;

class StockRequestController extends Controller
{
    public function __construct(
        private readonly GetStockRequests $getStockRequests,
        private readonly GetStockRequestDetail $getStockRequestDetail,
        private readonly CreateStockRequest $createStockRequest,
        private readonly ApproveStockRequest $approveStockRequest,
    ) {}

    public function index()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $stockRequests = $this->getStockRequests->execute($accessibleBranchIds, $filters);

        return Inertia::render('Warehouse/StockRequests/index', [
            'stockRequests' => $stockRequests,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $requestingWarehouses = Warehouse::with('branch')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('is_active', true)
            ->get();

        $destinationWarehouses = Warehouse::with('branch')
            ->whereHas('branch', fn ($q) => $q->where('is_headquarters', true))
            ->where('is_active', true)
            ->get();

        $productVariants = ProductVariant::with('product')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('is_active', true)
            ->get();

        return Inertia::render('Warehouse/StockRequests/create', [
            'requestingWarehouses' => $requestingWarehouses,
            'destinationWarehouses' => $destinationWarehouses,
            'productVariants' => $productVariants,
        ]);
    }

    public function store(StoreStockRequestRequest $request)
    {
        $this->createStockRequest->execute(
            $request->validated(),
            $request->user()->id,
        );

        return redirect()->route('warehouse.stock-requests.index')
            ->with('success', 'Permintaan stok berhasil dibuat.');
    }

    public function show(int $id)
    {
        $stockRequest = $this->getStockRequestDetail->execute($id);

        return Inertia::render('Warehouse/StockRequests/show', [
            'stockRequest' => $stockRequest,
        ]);
    }

    public function approve(int $id, ApproveStockRequestRequest $request)
    {
        $result = $this->approveStockRequest->execute(
            $id,
            $request->validated(),
            $request->user()->id,
        );

        $message = match ($result->status) {
            'approved' => 'Permintaan stok disetujui sepenuhnya.',
            'partially_approved' => 'Permintaan stok disetujui sebagian.',
            'rejected' => 'Permintaan stok ditolak.',
            default => 'Permintaan stok berhasil diproses.',
        };

        return redirect()->route('warehouse.stock-requests.show', $id)
            ->with('success', $message);
    }

    private function resolveBranchIds($user, string $tenantId): array
    {
        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
