<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseOrder\ApprovePurchaseOrder;
use Modules\Purchasing\Application\PurchaseOrder\CancelPurchaseOrder;
use Modules\Purchasing\Application\PurchaseOrder\CreatePurchaseOrder;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrderDetail;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrders;
use Modules\Purchasing\Application\PurchaseOrder\SendPurchaseOrder;
use Modules\Purchasing\Http\Requests\StorePurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly GetPurchaseOrders $getPurchaseOrders,
        private readonly GetPurchaseOrderDetail $getPurchaseOrderDetail,
        private readonly CreatePurchaseOrder $createPurchaseOrder,
        private readonly ApprovePurchaseOrder $approvePurchaseOrder,
        private readonly SendPurchaseOrder $sendPurchaseOrder,
        private readonly CancelPurchaseOrder $cancelPurchaseOrder,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $purchaseOrders = $this->getPurchaseOrders->execute($accessibleBranchIds, $filters);

        return Inertia::render('Purchasing/Orders/index', [
            'purchaseOrders' => $purchaseOrders,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/Orders/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
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

        return Inertia::render('Purchasing/Orders/show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $this->approvePurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.show', $id)
            ->with('success', 'Pesanan pembelian disetujui.');
    }

    public function send(int $id): RedirectResponse
    {
        $this->sendPurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.show', $id)
            ->with('success', 'Pesanan pembelian dikirim ke pemasok.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->cancelPurchaseOrder->execute($id);

        return redirect()->route('purchasing.orders.index')
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
