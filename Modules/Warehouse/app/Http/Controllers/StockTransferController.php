<?php

namespace Modules\Warehouse\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Models\ProductVariant;
use Modules\Warehouse\Application\StockTransfer\ApproveDirectTransfer;
use Modules\Warehouse\Application\StockTransfer\CreateDirectTransfer;
use Modules\Warehouse\Application\StockTransfer\GetStockTransferDetail;
use Modules\Warehouse\Application\StockTransfer\GetStockTransfers;
use Modules\Warehouse\Application\StockTransfer\ReceiveStockTransfer;
use Modules\Warehouse\Application\StockTransfer\ShipStockTransfer;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Http\Requests\ApproveDirectTransferRequest;
use Modules\Warehouse\Http\Requests\StoreDirectTransferRequest;
use Modules\Warehouse\Models\Warehouse;
use Modules\Warehouse\Services\InsufficientStockException;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly GetStockTransfers $getStockTransfers,
        private readonly GetStockTransferDetail $getStockTransferDetail,
        private readonly CreateDirectTransfer $createDirectTransfer,
        private readonly ApproveDirectTransfer $approveDirectTransfer,
        private readonly ShipStockTransfer $shipStockTransfer,
        private readonly ReceiveStockTransfer $receiveStockTransfer,
        private readonly GetTransactionApprovalStatus $approvalStatus,
    ) {}

    public function index()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = CompanyAccess::contextBranchIds(
            $user,
            $tenantId
        ) ?? CompanyAccess::accessibleBranchIds(
            $user,
            $tenantId
        );

        $filters = request()->only([
            'search',
            'status',
            'from_warehouse_id',
            'to_warehouse_id',
        ]);

        $stockTransfers = $this->getStockTransfers->execute(
            $accessibleBranchIds,
            $filters
        );

        return Inertia::render('Warehouse/StockTransfers/index', [
            'stockTransfers' => $stockTransfers,
            'filters' => $filters,
        ]);
    }

    public function show(int $id)
    {
        $stockTransfer = $this->getStockTransferDetail->execute($id);

        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        /*
         * Status approval via Approval module (tipe stock_transfer).
         * Jika ada mapping, approve/reject dilakukan via Inbox Approval
         * (ApprovalPanel) sesuai approver di rule, bukan via tombol lama.
         * Jika tidak ada mapping, pakai fallback lama: tombol approve
         * hanya untuk anggota HQ.
         */
        $approval = $this->approvalStatus->execute(
            'stock_transfer',
            (int) $stockTransfer->id,
            $user ? (int) $user->id : null,
        );

        $canApprove = $approval === null
            && $stockTransfer->status === StockTransferStatus::PendingApproval->value
            && $user
            && $user->can('warehouse.stock.transfer')
            && CompanyAccess::isActiveBranchHq($user, $tenantId);

        return Inertia::render('Warehouse/StockTransfers/show', [
            'stockTransfer' => $stockTransfer,
            'canApprove' => $canApprove,
            'approval' => $approval,
        ]);
    }

    public function create()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');

        $accessibleBranchIds = CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);

        $sourceWarehouses = Warehouse::with('branch')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('is_active', true)
            ->get();

        $destinationWarehouses = Warehouse::with('branch')
            ->where('is_active', true)
            ->get();

        $productVariants = ProductVariant::with('product')
            ->whereIn('branch_id', $accessibleBranchIds)
            ->where('is_active', true)
            ->get();

        return Inertia::render('Warehouse/StockTransfers/create', [
            'sourceWarehouses' => $sourceWarehouses,
            'destinationWarehouses' => $destinationWarehouses,
            'productVariants' => $productVariants,
        ]);
    }

    public function store(StoreDirectTransferRequest $request)
    {
        $user = request()->user();

        abort_unless(
            $user && $user->can('warehouse.stock.transfer'),
            403
        );

        try {
            $transfer = $this->createDirectTransfer->execute(
                $request->validated(),
                (int) $user->id,
                $user->name,
            );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }

        $message = $transfer->status === StockTransferStatus::Draft->value
            ? 'Transfer stok langsung berhasil dibuat (status: draft).'
            : 'Transfer stok berhasil dibuat dan menunggu persetujuan HO.';

        return redirect()
            ->route('warehouse.stock-transfers.index')
            ->with('success', $message);
    }

    public function approve(int $id, ApproveDirectTransferRequest $request)
    {
        /*
         * Endpoint ini hanya untuk fallback (transfer pending tanpa
         * Approval Rule). Jika transfer diatur oleh rule, arahkan
         * user ke Inbox Approval agar approver sesuai setting.
         */
        $fallbackGuard = $this->approvalStatus->execute(
            'stock_transfer',
            $id,
            $request->user() ? (int) $request->user()->id : null,
        );

        if ($fallbackGuard !== null) {
            return back()->withErrors([
                'approval' => 'Transfer ini diatur oleh Aturan Approval. Lakukan persetujuan via Inbox Approval.',
            ])->withInput();
        }

        try {
            $transfer = $this->approveDirectTransfer->execute(
                $id,
                $request->validated('decision'),
                (int) $request->user()->id
            );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }

        $message = $transfer->status === StockTransferStatus::Draft->value
            ? 'Transfer stok disetujui HO (status: draft).'
            : 'Transfer stok ditolak HO.';

        return redirect()
            ->route('warehouse.stock-transfers.show', $id)
            ->with('success', $message);
    }

    public function ship(int $id)
    {
        $user = request()->user();

        abort_unless(
            $user && $user->can('warehouse.stock.transfer'),
            403
        );

        try {
            $this->shipStockTransfer->execute(
                $id,
                (int) $user->id
            );

            return redirect()
                ->route('warehouse.stock-transfers.show', $id)
                ->with(
                    'success',
                    'Stock transfer berhasil dikirim (status: shipped).'
                );
        } catch (InsufficientStockException $e) {
            return back()
                ->withErrors([
                    'stock_transfer' => $e->getMessage(),
                ])
                ->withInput();
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }

    public function receive(int $id)
    {
        $user = request()->user();

        abort_unless(
            $user && $user->can('warehouse.stock.transfer'),
            403
        );

        $receivedItems = request()->validate([
            'received_items' => ['required', 'array', 'min:1'],
            'received_items.*.stock_transfer_item_id' => ['required', 'integer', 'exists:stock_transfer_items,id'],
            'received_items.*.qty_received' => ['required', 'integer', 'gte:0'],
        ])['received_items'];

        try {
            $this->receiveStockTransfer->execute(
                $id,
                $receivedItems,
                (int) $user->id
            );

            return redirect()
                ->route('warehouse.stock-transfers.show', $id)
                ->with(
                    'success',
                    'Stock transfer berhasil diterima.'
                );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }
}
