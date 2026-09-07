<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Product\Application\Product\GetProduct;
use Modules\Product\Application\Variant\CreateVariant;
use Modules\Product\Application\Variant\DeleteVariant;
use Modules\Product\Application\Variant\UpdateVariant;
use Modules\Product\Http\Requests\StoreVariantRequest;
use Modules\Product\Http\Requests\UpdateVariantRequest;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly GetProduct $getProduct,
        private readonly CreateVariant $createVariant,
        private readonly UpdateVariant $updateVariant,
        private readonly DeleteVariant $deleteVariant,
    ) {}

    public function store(StoreVariantRequest $request)
    {
        $validated = $request->validated();

        // Inherit branch from parent product
        if (! isset($validated['branch_id'])) {
            $product = $this->getProduct->execute($validated['product_id']);
            $validated['branch_id'] = $product['branch_id'];
        }

        $this->createVariant->execute($validated);

        return redirect()->back()->with('success', 'Variant created successfully.');
    }

    public function update(UpdateVariantRequest $request, int $product, int $variant)
    {
        $this->updateVariant->execute($variant, $request->validated());

        return redirect()->back()->with('success', 'Variant updated successfully.');
    }

    public function destroy(int $product, int $variant)
    {
        $this->deleteVariant->execute($variant);

        return redirect()->back()->with('success', 'Variant deleted successfully.');
    }
}
