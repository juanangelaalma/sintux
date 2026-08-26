<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
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
use Modules\Purchasing\Application\PurchaseRequest\GetPurchaseRequests;
use Modules\Purchasing\Http\Requests\StorePurchaseOrderRequest;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

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
        $activeBranchId = $accessibleBranchIds[0] ?? null;
        $branchWarehouses = $activeBranchId
            ? app(GetWarehouses::class)->all([$activeBranchId])
            : [];

        $purchaseRequests = app(GetPurchaseRequests::class)->execute($accessibleBranchIds, ['status' => 'approved'], 100);

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
            'activeBranch' => collect(CompanyAccess::accessibleBranches($user, $tenantId))->firstWhere('id', $activeBranchId),
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'warehouses' => $branchWarehouses,
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'purchaseRequests' => collect($purchaseRequests->items())->map(fn ($pr) => [
                'id' => $pr->id,
                'number' => $pr->number,
                'items' => $pr->items,
            ]),
            'paymentTerms' => $paymentTerms,
            'taxes' => app(GetPurchaseTaxes::class)->execute(),
            'productVariants' => app(GetPurchaseVariants::class)->execute(),
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseOrder->execute($validated, (string) $branchCode);

        return redirect()->route('purchasing.orders.index')
            ->with('success', 'Pesanan pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseOrder = $this->getPurchaseOrderDetail->execute($id);
        $approval = $this->getTransactionApprovalStatus->execute('purchase_order', $id, request()->user()?->id);

        return Inertia::render('Purchasing/Orders/show', [
            'purchaseOrder' => $purchaseOrder,
            'approval' => $approval,
        ]);
    }

    public function send(int $id): RedirectResponse
    {
        $this->sendPurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.show', $id)
            ->with('success', 'Pesanan pembelian ditandai dikirim ke supplier.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->cancelPurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.show', $id)
            ->with('success', 'Pesanan pembelian dibatalkan.');
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
