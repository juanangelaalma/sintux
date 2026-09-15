<?php

namespace Modules\Sales\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetSaleVariants;
use Modules\Sales\Application\SalesInvoice\CreateSalesInvoice;
use Modules\Sales\Application\SalesInvoice\GetSalesInvoiceDetail;
use Modules\Sales\Application\SalesInvoice\GetSalesInvoices;
use Modules\Sales\Http\Requests\StoreSalesInvoiceRequest;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class SalesInvoiceController extends Controller
{
    public function __construct(
        private readonly GetSalesInvoices $getSalesInvoices,
        private readonly GetSalesInvoiceDetail $getSalesInvoiceDetail,
        private readonly CreateSalesInvoice $createSalesInvoice,
        private readonly GetTransactionApprovalStatus $getTransactionApprovalStatus,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $branchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        return Inertia::render('Sales/Invoices/index', [
            'salesInvoices' => $this->getSalesInvoices->execute($branchIds, $filters),
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $branchIds = $this->resolveBranchIds($user, $tenantId);
        $activeBranch = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', (int) session('active_branch_id'));

        $warehouses = $activeBranch
            ? app(GetWarehouses::class)->optionsForSale((int) $activeBranch->id)
            : [];

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

        return Inertia::render('Sales/Invoices/create', [
            'activeBranch' => $activeBranch,
            'warehouses' => $warehouses,
            'customers' => app(GetContacts::class)->execute('customer', $branchIds),
            'employees' => app(GetContacts::class)->execute('employee', $branchIds),
            'productVariants' => app(GetSaleVariants::class)->execute($branchIds),
            'taxes' => app(TaxQuery::class)->listForSale(),
            'paymentTerms' => $paymentTerms,
        ]);
    }

    public function store(StoreSalesInvoiceRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $activeBranch = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', (int) session('active_branch_id'));

        abort_if(! $activeBranch, 422, 'Pilih cabang aktif terlebih dahulu.');

        $branchIds = $this->resolveBranchIds($user, $tenantId);

        $this->createSalesInvoice->execute(
            $validated,
            (int) $activeBranch->id,
            (string) $activeBranch->code,
            null,
            null,
            $branchIds
        );

        return redirect()->route('sales.invoices.index')
            ->with('success', 'Faktur penjualan berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $salesInvoice = $this->getSalesInvoiceDetail->execute($id, $this->resolveBranchIds($user, $tenantId));
        $approval = $this->getTransactionApprovalStatus->execute('sales_invoice', $id, request()->user()?->id);

        return Inertia::render('Sales/Invoices/show', [
            'salesInvoice' => $salesInvoice,
            'approval' => $approval,
        ]);
    }

    /**
     * Sales documents belong to the active branch: viewing follows the
     * branch the user switched to, never a merged cross-branch view.
     *
     * @return list<int>
     */
    private function resolveBranchIds(User $user, string $tenantId): array
    {
        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
