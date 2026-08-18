<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Application\CompanyAccess;
use Modules\Purchasing\Application\JoinPurchaseInvoice\CreateJoinPurchaseInvoice;
use Modules\Purchasing\Application\JoinPurchaseInvoice\GetJoinPurchaseInvoiceDetail;
use Modules\Purchasing\Application\JoinPurchaseInvoice\GetJoinPurchaseInvoices;
use Modules\Purchasing\Application\JoinPurchaseInvoice\ReadyJoinPurchaseInvoice;
use Modules\Purchasing\Http\Requests\StoreJoinPurchaseInvoiceRequest;

class JoinPurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly GetJoinPurchaseInvoices $getJoinPurchaseInvoices,
        private readonly GetJoinPurchaseInvoiceDetail $getJoinPurchaseInvoiceDetail,
        private readonly CreateJoinPurchaseInvoice $createJoinPurchaseInvoice,
        private readonly ReadyJoinPurchaseInvoice $readyJoinPurchaseInvoice,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $joinPurchaseInvoices = $this->getJoinPurchaseInvoices->execute($accessibleBranchIds, $filters);

        return Inertia::render('Purchasing/Joins/index', [
            'joinPurchaseInvoices' => $joinPurchaseInvoices,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        return Inertia::render('Purchasing/Joins/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
        ]);
    }

    public function store(StoreJoinPurchaseInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createJoinPurchaseInvoice->execute($validated, (string) $branchCode);

        return redirect()->route('purchasing.joins.index')
            ->with('success', 'Tukar faktur berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $joinPurchaseInvoice = $this->getJoinPurchaseInvoiceDetail->execute($id);

        return Inertia::render('Purchasing/Joins/show', [
            'joinPurchaseInvoice' => $joinPurchaseInvoice,
        ]);
    }

    public function ready(int $id): RedirectResponse
    {
        $this->readyJoinPurchaseInvoice->execute($id);

        return redirect()->route('purchasing.joins.show', $id)
            ->with('success', 'Tukar faktur siap diproses.');
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
