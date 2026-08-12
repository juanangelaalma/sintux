<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Product\Application\Uom\CreateUom;
use Modules\Product\Application\Uom\DeleteUom;
use Modules\Product\Application\Uom\GetUom;
use Modules\Product\Application\Uom\GetUoms;
use Modules\Product\Application\Uom\UpdateUom;
use Modules\Product\Http\Requests\StoreUomRequest;
use Modules\Product\Http\Requests\UpdateUomRequest;

class UomController extends Controller
{
    public function __construct(
        private readonly GetUoms $getUoms,
        private readonly GetUom $getUom,
        private readonly CreateUom $createUom,
        private readonly UpdateUom $updateUom,
        private readonly DeleteUom $deleteUom,
    ) {}

    public function index()
    {
        $filters = request()->only(['search', 'is_active']);
        $uoms = $this->getUoms->execute($filters);

        return Inertia::render('Product/Uoms/index', [
            'uoms' => $uoms,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Product/Uoms/create');
    }

    public function store(StoreUomRequest $request)
    {
        $this->createUom->execute($request->validated());

        return redirect()->route('product.uoms.index')
            ->with('success', 'UOM created successfully.');
    }

    public function edit(int $id)
    {
        $uom = $this->getUom->execute($id);

        return Inertia::render('Product/Uoms/edit', [
            'uom' => $uom,
        ]);
    }

    public function update(UpdateUomRequest $request, int $id)
    {
        $this->updateUom->execute($id, $request->validated());

        return redirect()->route('product.uoms.index')
            ->with('success', 'UOM updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->deleteUom->execute($id);

        return redirect()->back()->with('success', 'UOM deleted successfully.');
    }
}
