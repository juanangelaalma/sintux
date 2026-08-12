<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Product\Application\Variant\CreateVariant;
use Modules\Product\Application\Variant\DeleteVariant;
use Modules\Product\Application\Variant\UpdateVariant;
use Modules\Product\Http\Requests\StoreVariantRequest;
use Modules\Product\Http\Requests\UpdateVariantRequest;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly CreateVariant $createVariant,
        private readonly UpdateVariant $updateVariant,
        private readonly DeleteVariant $deleteVariant,
    ) {}

    public function store(StoreVariantRequest $request)
    {
        $this->createVariant->execute($request->validated());

        return redirect()->back()->with('success', 'Variant created successfully.');
    }

    public function update(UpdateVariantRequest $request, int $id)
    {
        $this->updateVariant->execute($id, $request->validated());

        return redirect()->back()->with('success', 'Variant updated successfully.');
    }

    public function destroy(int $id)
    {
        $this->deleteVariant->execute($id);

        return redirect()->back()->with('success', 'Variant deleted successfully.');
    }
}