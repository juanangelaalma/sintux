<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Product\Application\Category\GetCategories;
use Modules\Product\Application\Product\CreateProduct;
use Modules\Product\Application\Product\DeleteProduct;
use Modules\Product\Application\Product\GetProduct;
use Modules\Product\Application\Product\GetProducts;
use Modules\Product\Application\Product\UpdateProduct;
use Modules\Product\Application\Uom\GetUoms;
use Modules\Product\Application\Variant\GetVariants;
use Modules\Product\Http\Requests\StoreProductRequest;
use Modules\Product\Http\Requests\UpdateProductRequest;

class ProductController extends Controller
{
    public function __construct(
        private readonly GetProducts $getProducts,
        private readonly GetProduct $getProduct,
        private readonly GetCategories $getCategories,
        private readonly GetUoms $getUoms,
        private readonly GetVariants $getVariants,
        private readonly CreateProduct $createProduct,
        private readonly UpdateProduct $updateProduct,
        private readonly DeleteProduct $deleteProduct,
    ) {}

    public function index()
    {
        $filters = request()->only(['search', 'category_id', 'product_type', 'is_active']);
        $products = $this->getProducts->execute($filters);

        return Inertia::render('Product/Products/index', [
            'products' => $products,
            'categories' => $this->getCategories->all(),
            'uoms' => $this->getUoms->all(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        // Get all single products for bundle selection
        $allProducts = $this->getProducts->execute(['is_active' => true]);

        return Inertia::render('Product/Products/create', [
            'categories' => $this->getCategories->all(),
            'uoms' => $this->getUoms->all(),
            'availableProducts' => $allProducts['data'] ?? [],
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $this->createProduct->execute($request->validated());

        return redirect()->route('product.products.index')
            ->with('success', 'Product created successfully.');
    }

    public function edit(int $id)
    {
        $product = $this->getProduct->execute($id);
        $variants = $this->getVariants->execute($id);
        $allProducts = $this->getProducts->execute(['is_active' => true]);

        return Inertia::render('Product/Products/edit', [
            'product' => $product,
            'variants' => $variants,
            'categories' => $this->getCategories->all(false),
            'uoms' => $this->getUoms->all(false),
            'availableProducts' => array_values(array_filter(
                $allProducts['data'] ?? [],
                fn ($p) => $p['id'] !== $id
            )),
        ]);
    }

    public function update(UpdateProductRequest $request, int $id)
    {
        $this->updateProduct->execute($id, $request->validated());

        return redirect()->route('product.products.index')
            ->with('success', 'Product updated successfully.');
    }

    public function destroy(int $id)
    {
        $deleted = $this->deleteProduct->execute($id);

        if (! $deleted) {
            return redirect()->back()->with('error', 'Cannot delete product.');
        }

        return redirect()->back()->with('success', 'Product deleted successfully.');
    }
}
