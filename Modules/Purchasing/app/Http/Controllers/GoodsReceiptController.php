<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Company\Application\CompanyAccess;
use Modules\Purchasing\Application\GoodsReceipt\ApproveGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\ConfirmPhysicalQty;
use Modules\Purchasing\Application\GoodsReceipt\FetchGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\GetGoodsReceiptDetail;
use Modules\Purchasing\Application\GoodsReceipt\GetGoodsReceipts;
use Modules\Purchasing\Application\GoodsReceipt\GetReceivablePurchaseOrders;
use Modules\Purchasing\Application\GoodsReceipt\RejectGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\ReviseGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\SubmitGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\UpdateReceivedQty;
use Modules\Purchasing\Application\GoodsReceipt\VerifyBundleBarcode;
use Modules\Purchasing\Application\PurchaseInvoice\GetPurchaseSummary;
use Modules\Purchasing\Http\Requests\ConfirmPhysicalQtyRequest;
use Modules\Purchasing\Http\Requests\FetchGoodsReceiptRequest;
use Modules\Purchasing\Http\Requests\RejectGoodsReceiptRequest;
use Modules\Purchasing\Http\Requests\UpdateReceivedQtyRequest;
use Modules\Purchasing\Http\Requests\VerifyBundleRequest;
use Modules\Warehouse\Application\Warehouse\GetWarehouse;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GetGoodsReceipts $getGoodsReceipts,
        private readonly GetGoodsReceiptDetail $getGoodsReceiptDetail,
        private readonly FetchGoodsReceipt $fetchGoodsReceipt,
        private readonly VerifyBundleBarcode $verifyBundleBarcode,
        private readonly UpdateReceivedQty $updateReceivedQty,
        private readonly ConfirmPhysicalQty $confirmPhysicalQty,
        private readonly SubmitGoodsReceipt $submitGoodsReceipt,
        private readonly ReviseGoodsReceipt $reviseGoodsReceipt,
        private readonly ApproveGoodsReceipt $approveGoodsReceipt,
        private readonly RejectGoodsReceipt $rejectGoodsReceipt,
        private readonly GetPurchaseSummary $getPurchaseSummary,
    ) {}

    public function index(): Response
    {
        $branchIds = $this->listBranchIds();
        $filters = request()->only(['search', 'status']);

        return Inertia::render('Purchasing/GRNs/index', [
            'goodsReceipts' => $this->getGoodsReceipts->execute($branchIds, $filters),
            'filters' => $filters,
            'summary' => $this->getPurchaseSummary->execute($branchIds),
            'pendingGrnCount' => $this->getGoodsReceipts->pendingSubmittedCount($branchIds),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $branchId = $this->operatingBranchId();

        if (CompanyAccess::isHeadquartersBranch($branchId)) {
            return redirect()->route('purchasing.grns.index');
        }

        return Inertia::render('Purchasing/GRNs/create', [
            'purchaseOrders' => app(GetReceivablePurchaseOrders::class)->execute($branchId),
        ]);
    }

    public function fetch(FetchGoodsReceiptRequest $request): Response|RedirectResponse
    {
        $branchId = $this->operatingBranchId();
        $validated = $request->validated();
        $customer = $this->resolveCustomer($validated);

        $preview = $this->fetchGoodsReceipt->preview(
            (string) $validated['do_no'],
            $branchId,
            isset($validated['purchase_order_id']) ? (int) $validated['purchase_order_id'] : null,
            $customer,
        );

        if ($preview['existing_id'] !== null) {
            return redirect()->route('purchasing.grns.show', $preview['existing_id'])
                ->with('success', 'DO ini sudah pernah di-fetch, membuka data tersimpan.');
        }

        return Inertia::render('Purchasing/GRNs/preview', [
            'preview' => [
                'do_no' => (string) $validated['do_no'],
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'customer' => $customer,
                'po_number' => $preview['po']->number,
                'header' => [
                    'do_date' => $preview['payload']['do_date'],
                    'cust_name' => $preview['payload']['cust_name'],
                    'driver' => $preview['payload']['driver'],
                    'nopol' => $preview['payload']['nopol'],
                    'inv_no' => $preview['payload']['inv_no'],
                ],
                'items' => $preview['items'],
            ],
        ]);
    }

    public function store(FetchGoodsReceiptRequest $request): RedirectResponse
    {
        $branchId = $this->operatingBranchId();
        $branchCode = $this->operatingBranchCode($request->user());
        $validated = $request->validated();

        $grn = $this->fetchGoodsReceipt->execute(
            (string) $validated['do_no'],
            $branchId,
            $branchCode,
            isset($validated['purchase_order_id']) ? (int) $validated['purchase_order_id'] : null,
            $this->resolveCustomer($validated),
        );

        return redirect()->route('purchasing.grns.show', $grn->id)
            ->with('success', "{$grn->supplier_do_no} disimpan sebagai GRN draft, silakan verifikasi barang.");
    }

    public function show(int $id): Response
    {
        $grn = $this->getGoodsReceiptDetail->execute($id);
        $this->guardVisible($grn->branch_id);

        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);

        $warehouse = app(GetWarehouse::class)->execute(
            (int) $grn->warehouse_id,
            $accessibleBranchIds,
        );

        return Inertia::render('Purchasing/GRNs/show', [
            'goodsReceipt' => $grn,
            'warehouse' => $warehouse
                ? ['id' => $warehouse->id, 'code' => $warehouse->code, 'name' => $warehouse->name]
                : null,
        ]);
    }

    public function verify(VerifyBundleRequest $request, int $id): RedirectResponse
    {
        $this->verifyBundleBarcode->execute(
            $id,
            (string) $request->validated()['barcode'],
            $this->operatingBranchId(),
        );

        return redirect()->route('purchasing.grns.show', $id)
            ->with('success', 'Bundle terverifikasi.');
    }

    public function updateItemQty(UpdateReceivedQtyRequest $request, int $itemId): RedirectResponse
    {
        $item = $this->updateReceivedQty->updateItem(
            $itemId,
            (float) $request->validated()['qty_received'],
            $this->operatingBranchId(),
        );

        return redirect()->route('purchasing.grns.show', $item->goods_receipt_id)
            ->with('success', 'Qty diterima diperbarui.');
    }

    public function confirmQty(ConfirmPhysicalQtyRequest $request, int $itemId): RedirectResponse
    {
        $item = $this->confirmPhysicalQty->execute(
            $itemId,
            $this->operatingBranchId(),
            (bool) $request->validated()['confirmed'],
        );

        return redirect()->route('purchasing.grns.show', $item->goods_receipt_id)
            ->with('success', $item->qty_confirmed ? 'Hitung fisik dikonfirmasi.' : 'Konfirmasi hitung fisik dibatalkan.');
    }

    public function submit(int $id): RedirectResponse
    {
        $request = request();
        $grn = $this->submitGoodsReceipt->execute(
            $id,
            $this->operatingBranchId(),
            (int) $request->user()->id,
        );

        return redirect()->route('purchasing.grns.show', $grn->id)
            ->with('success', 'GRN terkirim ke HO untuk approval.');
    }

    public function revise(int $id): RedirectResponse
    {
        $grn = $this->reviseGoodsReceipt->execute(
            $id,
            $this->operatingBranchId(),
        );

        return redirect()->route('purchasing.grns.show', $grn->id)
            ->with('success', 'GRN dikembalikan ke draft, silakan koreksi lalu kirim ulang ke HO.');
    }

    public function inbox(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);

        $grns = $this->getGoodsReceipts->execute(
            $accessibleBranchIds,
            array_merge(request()->only(['search']), ['status' => request('status', 'submitted')])
        );

        $pendingCount = $this->getGoodsReceipts->pendingSubmittedCount($accessibleBranchIds);

        return Inertia::render('Purchasing/GrnInbox/index', [
            'goodsReceipts' => $grns,
            'filters' => request()->only(['search', 'status']),
            'pendingGrnCount' => $pendingCount,
        ]);
    }

    public function showInbox(int $id): Response
    {
        $grn = $this->getGoodsReceiptDetail->execute($id);
        $this->guardAccessible($grn->branch_id);

        return Inertia::render('Purchasing/GrnInbox/show', [
            'goodsReceipt' => $grn,
        ]);
    }

    public function approve(int $id): RedirectResponse
    {
        $grn = $this->getGoodsReceiptDetail->execute($id);
        $this->guardAccessible($grn->branch_id);

        $this->approveGoodsReceipt->execute($id, (int) request()->user()->id);

        return redirect()->route('purchasing.grn-inbox.show', $id)
            ->with('success', 'GRN disetujui, stok masuk HO dan transfer ke cabang berjalan.');
    }

    public function reject(RejectGoodsReceiptRequest $request, int $id): RedirectResponse
    {
        $grn = $this->getGoodsReceiptDetail->execute($id);
        $this->guardAccessible($grn->branch_id);

        $this->rejectGoodsReceipt->execute($id, (int) $request->user()->id, (string) $request->validated()['reason']);

        return redirect()->route('purchasing.grn-inbox.show', $id)
            ->with('success', 'GRN ditolak dan dikembalikan ke cabang untuk revisi.');
    }

    /**
     * @param  array{customer?: mixed}  $validated
     */
    private function resolveCustomer(array $validated): ?string
    {
        $customer = trim((string) ($validated['customer'] ?? ''));

        return $customer === '' ? null : $customer;
    }

    /**
     * @return list<int>
     */
    private function listBranchIds(): array
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $branchId = $this->operatingBranchId();

        if (CompanyAccess::isHeadquartersBranch($branchId)) {
            return CompanyAccess::accessibleBranchIds($user, $tenantId);
        }

        return [$branchId];
    }

    private function operatingBranchId(): int
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        return (int) (session('active_branch_id') ?? CompanyAccess::membershipBranchId($user, $tenantId));
    }

    private function operatingBranchCode(User $user): string
    {
        $tenantId = (string) session('active_tenant_id');
        $branchId = $this->operatingBranchId();

        return (string) (collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $branchId)?->code
            ?? collect(CompanyAccess::accessibleBranches($user, $tenantId))->first()?->code
            ?? '');
    }

    private function guardVisible(int $branchId): void
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        if (CompanyAccess::isHeadquartersBranch($this->operatingBranchId())) {
            $this->guardAccessible($branchId);

            return;
        }

        if ($branchId !== $this->operatingBranchId()) {
            abort(403);
        }
    }

    private function guardAccessible(int $branchId): void
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        if (! in_array($branchId, CompanyAccess::accessibleBranchIds($user, $tenantId), true)) {
            abort(403);
        }
    }
}
