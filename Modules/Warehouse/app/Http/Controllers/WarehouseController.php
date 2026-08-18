<?php

namespace Modules\Warehouse\Http\Controllers;

use App\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Application\Warehouse\CreateWarehouse;
use Modules\Warehouse\Application\Warehouse\DeleteWarehouse;
use Modules\Warehouse\Application\Warehouse\GetWarehouse;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;
use Modules\Warehouse\Application\Warehouse\UpdateWarehouse;
use Modules\Warehouse\Http\Requests\StoreWarehouseRequest;
use Modules\Warehouse\Http\Requests\UpdateWarehouseRequest;

class WarehouseController extends Controller
{
    public function __construct(
        private readonly GetWarehouses $getWarehouses,
        private readonly GetWarehouse $getWarehouse,
        private readonly CreateWarehouse $createWarehouse,
        private readonly UpdateWarehouse $updateWarehouse,
        private readonly DeleteWarehouse $deleteWarehouse,
    ) {}

    /**
     * @return list<int>
     */
    private function branchIds(): array
    {
        $tenantId = (string) tenant('id');
        /** @var User $user */
        $user = auth()->user();

        return CompanyAccess::contextBranchIds($user, $tenantId);
    }

    public function index()
    {
        $filters = request()->only(['search', 'warehouse_type']);

        return Inertia::render('Warehouse/Warehouses/index', [
            'warehouses' => $this->getWarehouses->execute($this->branchIds(), $filters),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Warehouse/Warehouses/create', [
            'branches' => $this->branches(),
        ]);
    }

    public function store(StoreWarehouseRequest $request)
    {
        $this->createWarehouse->execute($request->validated());

        return redirect()->route('warehouse.warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function edit(int $id)
    {
        $warehouse = $this->getWarehouse->execute($id, $this->branchIds());
        abort_unless($warehouse, 404);

        return Inertia::render('Warehouse/Warehouses/edit', [
            'warehouse' => $warehouse,
            'branches' => $this->branches(),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, int $id)
    {
        $this->updateWarehouse->execute($id, $request->validated(), $this->branchIds());

        return redirect()->route('warehouse.warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(int $id)
    {
        $deleted = $this->deleteWarehouse->execute($id, $this->branchIds());

        if (! $deleted) {
            return redirect()->back()->with('error', 'Cannot delete warehouse with stock balance.');
        }

        return redirect()->back()->with('success', 'Warehouse deleted successfully.');
    }

    /**
     * @return list<array{id: int, name: string, code: string, is_headquarters: bool}>
     */
    private function branches(): array
    {
        return DB::table('branches')
            ->whereIn('id', $this->branchIds())
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_headquarters'])
            ->map(fn ($branch) => [
                'id' => (int) $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_headquarters' => (bool) $branch->is_headquarters,
            ])
            ->all();
    }
}
