<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Application\PurchaseOrder\CancelPurchaseOrder;
use Modules\Purchasing\Application\PurchaseOrder\CreatePurchaseOrder;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrderDetail;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrders;
use Modules\Purchasing\Application\PurchaseOrder\SendPurchaseOrder;
use Modules\Purchasing\Application\PurchaseTag\GetPurchaseTags;
use Modules\Purchasing\Http\Requests\StorePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly GetPurchaseOrders $getPurchaseOrders,
        private readonly GetPurchaseOrderDetail $getPurchaseOrderDetail,
        private readonly CreatePurchaseOrder $createPurchaseOrder,
        private readonly SendPurchaseOrder $sendPurchaseOrder,
        private readonly CancelPurchaseOrder $cancelPurchaseOrder,
        private readonly GetPurchaseSummary $getPurchaseSummary,
        private readonly GetTransactionApprovalStatus $getTransactionApprovalStatus,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $purchaseOrders = $this->getPurchaseOrders->execute($accessibleBranchIds, $filters);
        $summary = $this->getPurchaseSummary->execute($accessibleBranchIds);

        return Inertia::render('Purchasing/Orders/index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $allBranches = CompanyAccess::accessibleBranches($user, $tenantId);
        $hqBranch = collect($allBranches)->firstWhere('is_headquarters', true) ?? $allBranches[0] ?? null;
        $hqBranchId = $hqBranch?->id;

        // Warehouses grouped by branch (only Regular for PO)
        $allRegularWarehouses = DB::table('warehouses')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('warehouse_type', 'regular')
            ->where('is_active', true)
            ->get(['id', 'branch_id', 'code', 'name']);

        $warehousesByBranch = collect($allRegularWarehouses)->groupBy('branch_id')->map(
            fn ($group) => $group->map(fn ($w) => ['id' => (int) $w->id, 'code' => $w->code, 'name' => $w->name])->values()->all()
        )->all();

        $hqWarehouse = $hqBranchId ? ($warehousesByBranch[$hqBranchId][0] ?? null) : null;

        $paymentTerms = [
            ['id' => 'COD', 'name' => 'Cash on Delivery (COD)'],
            ['id' => 'NET 15', 'name' => 'NET 15 Hari'],
            ['id' => 'NET 30', 'name' => 'NET 30 Hari'],
            ['id' => 'NET 45', 'name' => 'NET 45 Hari'],
            ['id' => 'NET 60', 'name' => 'NET 60 Hari'],
            ['id' => 'NET 90', 'name' => 'NET 90 Hari'],
            ['id' => 'NET 120', 'name' => 'NET 120 Hari'],
            ['id' => 'NET 180', 'name' => 'NET 180 Hari'],
            ['id' => 'Custom', 'name' => 'NET Custom Hari'],
        ];

        return Inertia::render('Purchasing/Orders/create', [
            'hqBranch' => $hqBranch,
            'activeBranch' => $hqBranch,
            'branches' => $allBranches,
            'warehouses' => $hqWarehouse ? [$hqWarehouse] : [],
            'warehousesByBranch' => $warehousesByBranch,
            'hqWarehouse' => $hqWarehouse,
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'paymentTerms' => $paymentTerms,
            'taxes' => app(GetPurchaseTaxes::class)->execute(),
            'productVariants' => app(GetPurchaseVariants::class)->execute($accessibleBranchIds),
            'tags' => app(GetPurchaseTags::class)->execute(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseOrder->execute($validated, (string) $branchCode, null, null, $accessibleBranchIds);

        return redirect()->route('purchasing.orders.index')
            ->with('success', 'Pesanan pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseOrder = $this->getPurchaseOrderDetail->execute($id);
        $this->ensureBranchAccess($purchaseOrder->branch_id);
        $approval = $this->getTransactionApprovalStatus->execute('purchase_order', $id, request()->user()?->id);

        return Inertia::render('Purchasing/Orders/show', [
            'purchaseOrder' => $purchaseOrder,
            'approval' => $approval,
        ]);
    }

    public function send(int $id): RedirectResponse
    {
        $purchaseOrder = $this->getPurchaseOrderDetail->execute($id);
        $this->ensureBranchAccess($purchaseOrder->branch_id);
        $this->sendPurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.show', $id)
            ->with('success', 'Pesanan pembelian ditandai dikirim ke supplier.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $purchaseOrder = $this->getPurchaseOrderDetail->execute($id);
        $this->ensureBranchAccess($purchaseOrder->branch_id);
        $this->cancelPurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.show', $id)
            ->with('success', 'Pesanan pembelian dibatalkan.');
    }

    private function ensureBranchAccess(int $branchId): void
    {
        $user = request()->user();
        if (! $user) {
            abort(403, 'Akses ditolak.');
        }
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        if (! in_array($branchId, $accessibleBranchIds, true)) {
            abort(403, 'Cabang tidak berada dalam cakupan akses Anda.');
        }
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
