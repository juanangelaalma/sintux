<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Application\PurchaseRequest\CancelPurchaseRequest;
use Modules\Purchasing\Application\PurchaseRequest\CreatePurchaseRequest;
use Modules\Purchasing\Application\PurchaseRequest\GetPurchaseRequestDetail;
use Modules\Purchasing\Application\PurchaseRequest\GetPurchaseRequests;
use Modules\Purchasing\Http\Requests\StorePurchaseRequestRequest;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly GetPurchaseRequests $getPurchaseRequests,
        private readonly GetPurchaseRequestDetail $getPurchaseRequestDetail,
        private readonly CreatePurchaseRequest $createPurchaseRequest,
        private readonly CancelPurchaseRequest $cancelPurchaseRequest,
        private readonly GetPurchaseSummary $getPurchaseSummary,
        private readonly GetTransactionApprovalStatus $getTransactionApprovalStatus,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $purchaseRequests = $this->getPurchaseRequests->execute($accessibleBranchIds, $filters);
        $summary = $this->getPurchaseSummary->execute($accessibleBranchIds);

        return Inertia::render('Purchasing/Requests/index', [
            'purchaseRequests' => $purchaseRequests,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/Requests/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'productVariants' => app(GetPurchaseVariants::class)->execute($accessibleBranchIds),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseRequest->execute($validated, (string) $branchCode, null, null, $accessibleBranchIds);

        return redirect()->route('purchasing.requests.index')
            ->with('success', 'Permintaan pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseRequest = $this->getPurchaseRequestDetail->execute($id);
        $approval = $this->getTransactionApprovalStatus->execute('purchase_request', $id, request()->user()?->id);

        return Inertia::render('Purchasing/Requests/show', [
            'purchaseRequest' => $purchaseRequest,
            'approval' => $approval,
        ]);
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->cancelPurchaseRequest->execute($id);

        return redirect()->route('purchasing.requests.show', $id)
            ->with('success', 'Permintaan pembelian dibatalkan.');
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
