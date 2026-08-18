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
use Modules\Purchasing\Application\GoodsReceipt\CreateGoodsReceipt;
use Modules\Purchasing\Application\GoodsReceipt\GetGoodsReceiptDetail;
use Modules\Purchasing\Application\GoodsReceipt\GetGoodsReceipts;
use Modules\Purchasing\Application\GoodsReceipt\PostGoodsReceipt;
use Modules\Purchasing\Http\Requests\StoreGoodsReceiptRequest;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GetGoodsReceipts $getGoodsReceipts,
        private readonly GetGoodsReceiptDetail $getGoodsReceiptDetail,
        private readonly CreateGoodsReceipt $createGoodsReceipt,
        private readonly PostGoodsReceipt $postGoodsReceipt,
    ) {}

    public function index(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $filters = request()->only(['search', 'status']);

        $goodsReceipts = $this->getGoodsReceipts->execute($accessibleBranchIds, $filters);

        return Inertia::render('Purchasing/GRNs/index', [
            'goodsReceipts' => $goodsReceipts,
            'filters' => $filters,
        ]);
    }

    public function create(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        return Inertia::render('Purchasing/GRNs/create', [
            'branches' => CompanyAccess::accessibleBranches($user, $tenantId),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'productVariants' => app(GetPurchaseVariants::class)->execute(),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $this->createGoodsReceipt->execute($validated, (string) $branchCode);

        return redirect()->route('purchasing.grns.index')
            ->with('success', 'Penerimaan barang berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $goodsReceipt = $this->getGoodsReceiptDetail->execute($id);

        return Inertia::render('Purchasing/GRNs/show', [
            'goodsReceipt' => $goodsReceipt,
        ]);
    }

    public function post(int $id): RedirectResponse
    {
        $this->postGoodsReceipt->execute($id);

        return redirect()->route('purchasing.grns.show', $id)
            ->with('success', 'Penerimaan barang diposting dan stok telah diperbarui.');
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
