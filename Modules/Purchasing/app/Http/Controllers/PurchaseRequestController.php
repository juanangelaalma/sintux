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
use Modules\Purchasing\Application\PurchaseRequest\ApprovePurchaseRequest;
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
        private readonly ApprovePurchaseRequest $approvePurchaseRequest,
        private readonly CancelPurchaseRequest $cancelPurchaseRequest,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $purchaseRequests = $this->getPurchaseRequests->execute($accessibleBranchIds, $filters);

        return Inertia::render('Purchasing/Requests/index', [
            'purchaseRequests' => $purchaseRequests,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/Requests/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'productVariants' => app(GetPurchaseVariants::class)->execute(),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseRequest->execute($validated, (string) $branchCode);

        return redirect()->route('purchasing.requests.index')
            ->with('success', 'Permintaan pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseRequest = $this->getPurchaseRequestDetail->execute($id);

        return Inertia::render('Purchasing/Requests/show', [
            'purchaseRequest' => $purchaseRequest,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $this->approvePurchaseRequest->execute($id);

        return redirect()->route('purchasing.requests.show', $id)
            ->with('success', 'Permintaan pembelian disetujui.');
    }

    public function cancel(int $id): RedirectResponse
    {
        $this->cancelPurchaseRequest->execute($id);

        return redirect()->route('purchasing.requests.index')
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
