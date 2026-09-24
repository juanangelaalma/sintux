<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Purchasing\Application\PurchaseReturn\CreatePurchaseReturn;
use Modules\Purchasing\Application\PurchaseReturn\GetPurchaseReturnDetail;
use Modules\Purchasing\Application\PurchaseReturn\GetReturnableItems;
use Modules\Purchasing\Application\PurchaseTag\GetPurchaseTags;
use Modules\Purchasing\Http\Requests\StorePurchaseReturnRequest;
use Modules\Purchasing\Models\PurchaseReturnAttachment;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PurchaseReturnController extends Controller
{
    public function __construct(
        private readonly GetReturnableItems $returnableItems,
        private readonly GetPurchaseReturnDetail $purchaseReturnDetail,
        private readonly CreatePurchaseReturn $createPurchaseReturn,
        private readonly GetTransactionApprovalStatus $getTransactionApprovalStatus,
    ) {}

    public function new(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);
        $hqBranchId = CompanyAccess::headquartersBranchId();

        $prefillInvoice = null;
        $prefillError = null;
        $invoiceId = request()->query('createdFrom');

        if ($invoiceId) {
            try {
                $prefillInvoice = $this->returnableItems->execute((int) $invoiceId);
            } catch (ValidationException $e) {
                $prefillError = collect($e->errors())->flatten()->first()
                    ?: 'Faktur tidak dapat diretur.';
            }
        }

        $warehouses = $hqBranchId ? app(GetWarehouses::class)->optionsForReceipt($hqBranchId) : [];

        return Inertia::render('Purchasing/Returns/create', [
            'hqBranchId' => $hqBranchId,
            'warehouses' => $warehouses,
            'tags' => app(GetPurchaseTags::class)->execute(),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'prefillInvoice' => $prefillInvoice,
            'prefillError' => $prefillError,
            'availability' => $this->availabilityMap($warehouses, $prefillInvoice),
        ]);
    }

    public function store(StorePurchaseReturnRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $branchCode = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', $validated['branch_id'])
            ->code ?? '';

        $purchaseReturn = $this->createPurchaseReturn->execute(
            $validated,
            (string) $branchCode,
            $user->id,
            $user->name,
            $accessibleBranchIds
        );

        foreach ($request->file('attachments', []) as $file) {
            $path = $file->store('purchase-returns/'.$purchaseReturn->id, 'local');

            PurchaseReturnAttachment::create([
                'purchase_return_id' => $purchaseReturn->id,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return redirect()->route('purchasing.returns.show', $purchaseReturn->id)
            ->with('success', 'Retur pembelian berhasil dibuat.');
    }

    public function show(int $id): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $detail = $this->purchaseReturnDetail->execute($id, $accessibleBranchIds);
        $approval = $this->getTransactionApprovalStatus->execute('purchase_return', $id, request()->user()?->id);

        return Inertia::render('Purchasing/Returns/show', [
            'purchaseReturn' => $detail,
            'approval' => $approval,
        ]);
    }

    public function downloadAttachment(int $returnId, int $attachmentId): StreamedResponse
    {
        $attachment = PurchaseReturnAttachment::where('purchase_return_id', $returnId)
            ->findOrFail($attachmentId);

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path)
        );
    }

    /**
     * Stok tersedia per gudang per varian untuk baris prefill, agar form
     * bisa membatasi qty sebelum submit (validasi final tetap di backend).
     *
     * @param  list<array{id: int}>  $warehouses
     * @param  array{items: list<array{product_variant_id: int}>}|null  $prefillInvoice
     * @return array<int, array<int, int>>
     */
    private function availabilityMap(array $warehouses, ?array $prefillInvoice): array
    {
        if ($prefillInvoice === null) {
            return [];
        }

        $variantIds = collect($prefillInvoice['items'] ?? [])
            ->pluck('product_variant_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if ($variantIds === []) {
            return [];
        }

        $map = [];

        foreach ($warehouses as $warehouse) {
            $map[(int) $warehouse['id']] = app(GetAvailableStock::class)->forItems((int) $warehouse['id'], $variantIds);
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    private function resolveBranchIds(User $user, string $tenantId): array
    {
        return CompanyAccess::accessibleBranchIds($user, $tenantId);
    }
}
