<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseInvoice\CreatePurchaseInvoice;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseInvoiceDetail;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseInvoices;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrderOptions;
use Modules\Purchasing\Http\Requests\StorePurchaseInvoiceRequest;

class PurchaseInvoiceController extends Controller
{
    public function __construct(
        private readonly GetPurchaseInvoices $getPurchaseInvoices,
        private readonly GetPurchaseInvoiceDetail $getPurchaseInvoiceDetail,
        private readonly CreatePurchaseInvoice $createPurchaseInvoice,
        private readonly GetPurchaseSummary $getPurchaseSummary,
        private readonly GetTransactionApprovalStatus $getTransactionApprovalStatus,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $purchaseInvoices = $this->getPurchaseInvoices->execute($accessibleBranchIds, $filters);
        $summary = $this->getPurchaseSummary->execute($accessibleBranchIds);

        return Inertia::render('Purchasing/Invoices/index', [
            'purchaseInvoices' => $purchaseInvoices,
            'filters' => $filters,
            'summary' => $summary,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/Invoices/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'productVariants' => app(GetPurchaseVariants::class)->execute(),
            'purchaseOrders' => app(GetPurchaseOrderOptions::class)->execute($accessibleBranchIds),
        ]);
    }

    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseInvoice->execute($validated, (string) $branchCode);

        return redirect()->route('purchasing.invoices.index')
            ->with('success', 'Faktur pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseInvoice = $this->getPurchaseInvoiceDetail->execute($id);
        $approval = $this->getTransactionApprovalStatus->execute('purchase_invoice', $id, request()->user()?->id);

        return Inertia::render('Purchasing/Invoices/show', [
            'purchaseInvoice' => $purchaseInvoice,
            'approval' => $approval,
        ]);
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
