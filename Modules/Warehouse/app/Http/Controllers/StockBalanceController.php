<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Company\Access\CompanyAccess;
use Modules\Warehouse\Application\StockBalance\GetStockBalances;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class StockBalanceController extends Controller
{
    public function __construct(
        private readonly GetStockBalances $getStockBalances,
        private readonly GetWarehouses $getWarehouses,
    ) {}

    /**
     * @return list<int>
     */
    private function branchIds(): array
    {
        $tenantId = (string) tenant('id');
        /** @var User $user */
        $user = auth()->user();

        return CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
    }

    public function index()
    {
        $filters = request()->only(['warehouse_id', 'search']);

        return Inertia::render('Warehouse/StockBalances/index', [
            'balances' => $this->getStockBalances->execute($this->branchIds(), $filters),
            'warehouses' => $this->getWarehouses->all($this->branchIds()),
            'filters' => $filters,
        ]);
    }
}
