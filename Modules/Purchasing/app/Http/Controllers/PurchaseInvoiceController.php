<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseInvoice\CreatePurchaseInvoice;
use Modules\Purchasing\Application\PurchaseInvoice\GetInvoicePrefillFromGrn;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseInvoiceDetail;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseInvoices;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Application\PurchaseOrder\GetPurchaseOrderOptions;
use Modules\Purchasing\Application\PurchaseReturn\GetInvoiceReturns;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
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

        $prefillGrn = null;
        $prefillError = null;
        $grnId = request()->query('goods_receipt_id');

        if ($grnId) {
            try {
                $prefillGrn = app(GetInvoicePrefillFromGrn::class)->execute((int) $grnId);
            } catch (ValidationException $e) {
                $prefillError = collect($e->errors())->flatten()->first()
                    ?: 'GRN tidak dapat dijadikan faktur.';
            }
        }

        return Inertia::render('Purchasing/Invoices/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'productVariants' => app(GetPurchaseVariants::class)->execute($accessibleBranchIds),
            'purchaseOrders' => app(GetPurchaseOrderOptions::class)->execute($accessibleBranchIds),
            'taxes' => app(TaxQuery::class)->listForPurchase(),
            'prefillGrn' => $prefillGrn,
            'prefillError' => $prefillError,
        ]);
    }

    public function store(StorePurchaseInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createPurchaseInvoice->execute($validated, (string) $branchCode, null, null, $accessibleBranchIds);

        return redirect()->route('purchasing.invoices.index')
            ->with('success', 'Faktur pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $purchaseInvoice = $this->getPurchaseInvoiceDetail->execute($id);
        $approval = $this->getTransactionApprovalStatus->execute('purchase_invoice', $id, request()->user()?->id);
        $returns = app(GetInvoiceReturns::class)->execute($id);

        $returnableQty = $purchaseInvoice->items->sum(
            fn ($item) => max(0.0, (float) $item->qty - (float) ($item->qty_returned ?? 0))
        );

        $invoiceOutstanding = max(
            0.0,
            (float) $purchaseInvoice->total
                - (float) ($purchaseInvoice->paid_amount ?? 0)
                - (float) ($purchaseInvoice->returned_amount ?? 0)
        );

        $canPay = in_array($purchaseInvoice->status, [
            PurchaseInvoiceStatus::Approved,
            PurchaseInvoiceStatus::PartiallyPaid,
        ], true) && $invoiceOutstanding > 0.0001;

        return Inertia::render('Purchasing/Invoices/show', [
            'purchaseInvoice' => $purchaseInvoice,
            'approval' => $approval,
            'paymentContext' => [
                'canPay' => $canPay,
                'outstanding' => $invoiceOutstanding,
            ],
            'returnContext' => [
                'canReturn' => in_array($purchaseInvoice->status, [
                    PurchaseInvoiceStatus::Approved,
                    PurchaseInvoiceStatus::PartiallyPaid,
                ], true) && $returnableQty > 0.0001,
                'returns' => $returns,
            ],
        ]);
    }

    /**
     * Purchase documents are cross-branch operations performed by HQ:
     * branches, variants, and allocation targets span every accessible branch.
     *
     * @return list<int>
     */
    private function resolveBranchIds(User $user, string $tenantId): array
    {
        return CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
