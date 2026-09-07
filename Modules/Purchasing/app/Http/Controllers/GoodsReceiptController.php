<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Application\CompanyAccess;
use Modules\Purchasing\Application\GoodsReceipt\CreateGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\GetGoodsReceiptDetail;
use Modules\Purchasing\Application\GoodsReceipt\GetGoodsReceipts;
use Modules\Purchasing\Application\GoodsReceipt\PostGoodsReceipt;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrderOptions;
use Modules\Purchasing\Http\Requests\StoreGoodsReceiptRequest;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GetGoodsReceipts $getGoodsReceipts,
        private readonly GetGoodsReceiptDetail $getGoodsReceiptDetail,
        private readonly CreateGoodsReceipt $createGoodsReceipt,
        private readonly PostGoodsReceipt $postGoodsReceipt,
        private readonly GetPurchaseSummary $getPurchaseSummary,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $goodsReceipts = $this->getGoodsReceipts->execute($accessibleBranchIds, $filters);
        $summary = $this->getPurchaseSummary->execute($accessibleBranchIds);

        return Inertia::render('Purchasing/GRNs/index', [
            'goodsReceipts' => $goodsReceipts,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/GRNs/create', [
            'warehouses' => app(GetWarehouses::class)->execute($accessibleBranchIds),
            'purchaseOrders' => app(GetPurchaseOrderOptions::class)->execute($accessibleBranchIds),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->first()
            ->code ?? '';

        $this->createGoodsReceipt->execute($validated, (string) $branchCode, $accessibleBranchIds);

        return redirect()->route('purchasing.grns.index')
            ->with('success', 'Penerimaan barang berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $goodsReceipt = $this->getGoodsReceiptDetail->execute($id);

        return Inertia::render('Purchasing/GRNs/show', [
            'goodsReceipt' => $goodsReceipt,
        ]);
    }

    public function post(int $id): RedirectResponse
    {
        $this->postGoodsReceipt->execute($id);

        return redirect()->route('purchasing.grns.show', $id)
            ->with('success', 'Stok penerimaan barang berhasil diposting.');
    }

    /**
     * @return list<int>
     */
    private function resolveBranchIds(User $user, string $tenantId): array
    {
        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
