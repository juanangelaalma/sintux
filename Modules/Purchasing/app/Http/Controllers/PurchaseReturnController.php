<?php

namespace Modules\Purchasing\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
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
use Modules\Purchasing\Application\PurchaseReturn\ResolveReturnVariant;
use Modules\Purchasing\Application\PurchaseTag\GetPurchaseTags;
use Modules\Purchasing\Http\Requests\StorePurchaseReturnRequest;
use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Purchasing\Models\PurchaseReturnAttachment;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Application\StockTransfer\GetStockTransfers;
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
        $selectedTransferId = request()->query('returnTransfer') ? (int) request()->query('returnTransfer') : null;

        return Inertia::render('Purchasing/Returns/create', [
            'hqBranchId' => $hqBranchId,
            'warehouses' => $warehouses,
            'tags' => app(GetPurchaseTags::class)->execute(),
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'prefillInvoice' => $prefillInvoice,
            'prefillError' => $prefillError,
            'transfers' => $this->transferOptions($accessibleBranchIds, $warehouses),
            'selectedTransferId' => $selectedTransferId,
            'availability' => $this->availabilityMap($warehouses, $prefillInvoice, $hqBranchId, $selectedTransferId),
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

        $purchaseReturn = null;

        DB::transaction(function () use (
            $validated, $branchCode, $user, $accessibleBranchIds, $request, &$purchaseReturn
        ): void {
            $purchaseReturn = $this->createPurchaseReturn->execute(
                $validated,
                (string) $branchCode,
                $user->id,
                $user->name,
                $accessibleBranchIds
            );

            // Lampiran disimpan dalam transaksi yang sama dengan dokumen
            // retur: gagal upload = seluruh retur rollback, bukan retur
            // finalized tanpa lampiran.
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
        });

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
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = $this->resolveBranchIds($user, $tenantId);

        $purchaseReturn = PurchaseReturn::findOrFail($returnId);

        if (! in_array((int) $purchaseReturn->branch_id, $accessibleBranchIds, true)) {
            abort(404);
        }

        $attachment = PurchaseReturnAttachment::where('purchase_return_id', $returnId)
            ->findOrFail($attachmentId);

        return Storage::disk('local')->download(
            $attachment->path,
            $attachment->original_name ?: basename($attachment->path)
        );
    }

    /**
     * Opsi transfer retur: transfer received yang masuk gudang HO.
     *
     * @param  list<int>  $accessibleBranchIds
     * @param  list<array{id: int}>  $warehouses
     * @return list<array{id: int, number: string, from_warehouse_name: string}>
     */
    private function transferOptions(array $accessibleBranchIds, array $warehouses): array
    {
        $warehouseIds = collect($warehouses)->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($warehouseIds === []) {
            return [];
        }

        $options = [];

        foreach ($warehouseIds as $warehouseId) {
            $page = app(GetStockTransfers::class)->execute(
                $accessibleBranchIds,
                ['status' => 'received', 'to_warehouse_id' => $warehouseId],
                50
            );

            foreach ($page->items() as $transfer) {
                $options[] = [
                    'id' => (int) $transfer->id,
                    'number' => (string) ($transfer->number ?? ('#'.$transfer->id)),
                    'from_warehouse_name' => (string) ($transfer->fromWarehouse?->name ?? ''),
                ];
            }
        }

        return $options;
    }

    /**
     * Stok tersedia per gudang per varian untuk baris prefill, agar form
     * bisa membatasi qty sebelum submit (validasi final tetap di backend).
     * Bila transfer dipilih, angka dibatasi layer transfer itu (provenance).
     *
     * @param  list<array{id: int}>  $warehouses
     * @param  array{items: list<array{product_variant_id: int}>}|null  $prefillInvoice
     * @return array<int, array<int, int>>
     */
    private function availabilityMap(array $warehouses, ?array $prefillInvoice, ?int $hqBranchId, ?int $transferId): array
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
            $warehouseId = (int) $warehouse['id'];

            if ($transferId !== null && $hqBranchId !== null) {
                $resolved = [];

                foreach ($variantIds as $variantId) {
                    $hit = app(ResolveReturnVariant::class)->execute($variantId, $hqBranchId, $transferId);
                    $resolved[$variantId] = $hit ?? -1;
                }

                $filtered = app(GetAvailableStock::class)->forItemsFromTransfer(
                    $warehouseId,
                    array_values(array_filter($resolved, fn ($id) => $id > 0)),
                    $transferId
                );

                $row = [];

                foreach ($resolved as $variantId => $stockVariantId) {
                    $row[$variantId] = $stockVariantId > 0 ? ($filtered[$stockVariantId] ?? 0) : 0;
                }

                $map[$warehouseId] = $row;
            } else {
                $map[$warehouseId] = app(GetAvailableStock::class)->forItems($warehouseId, $variantIds);
            }
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
