<?php

namespace Modules\Warehouse\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Application\StockTransfer\GetStockTransfers;
use Modules\Warehouse\Application\StockTransfer\GetStockTransferDetail;
use Modules\Warehouse\Application\StockTransfer\ReceiveStockTransfer;
use Modules\Warehouse\Application\StockTransfer\ShipStockTransfer;
use Modules\Warehouse\Services\InsufficientStockException;

class StockTransferController extends Controller
{
    public function __construct(
        private readonly GetStockTransfers $getStockTransfers,
        private readonly GetStockTransferDetail $getStockTransferDetail,
        private readonly ShipStockTransfer $shipStockTransfer,
        private readonly ReceiveStockTransfer $receiveStockTransfer,
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

        return Inertia::render('Warehouse/StockTransfers/show', [
            'stockTransfer' => $stockTransfer,
        ]);
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

        try {
            $this->receiveStockTransfer->execute(
                $id,
                (int) $user->id
            );

            return redirect()
                ->route('warehouse.stock-transfers.show', $id)
                ->with(
                    'success',
                    'Stock transfer berhasil diterima (status: received).'
                );
        } catch (ValidationException $e) {
            return back()
                ->withErrors($e->errors())
                ->withInput();
        }
    }
}