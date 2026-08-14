<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Product\Application\Brand\CreateBrand;
use Modules\Product\Application\Brand\DeleteBrand;
use Modules\Product\Application\Brand\GetBrand;
use Modules\Product\Application\Brand\GetBrands;
use Modules\Product\Application\Brand\UpdateBrand;
use Modules\Product\Http\Requests\StoreBrandRequest;
use Modules\Product\Http\Requests\UpdateBrandRequest;

class BrandController extends Controller
{
    public function __construct(
        private readonly GetBrands $getBrands,
        private readonly GetBrand $getBrand,
        private readonly CreateBrand $createBrand,
        private readonly UpdateBrand $updateBrand,
        private readonly DeleteBrand $deleteBrand,
    ) {}

    public function index()
    {
        $filters = request()->only(['search', 'is_active']);
        $brands = $this->getBrands->execute($filters);

        return Inertia::render('Product/Brands/index', [
            'brands' => $brands,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Product/Brands/create');
    }

    public function store(StoreBrandRequest $request)
    {
        $this->createBrand->execute($request->validated());

        return redirect()->route('product.brands.index')
            ->with('success', 'Brand created successfully.');
    }

    public function edit(int $id)
    {
        $brand = $this->getBrand->execute($id);

        return Inertia::render('Product/Brands/edit', [
            'brand' => $brand,
        ]);
    }

    public function update(UpdateBrandRequest $request, int $id)
    {
        $this->updateBrand->execute($id, $request->validated());

        return redirect()->route('product.brands.index')
            ->with('success', 'Brand updated successfully.');
    }

    public function destroy(int $id)
    {
        $deleted = $this->deleteBrand->execute($id);

        if (! $deleted) {
            return redirect()->back()->with('error', 'Cannot delete brand still used by products.');
        }

        return redirect()->back()->with('success', 'Brand deleted successfully.');
    }
}
