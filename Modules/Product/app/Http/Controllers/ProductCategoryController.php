<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Modules\Product\Application\Category\CreateCategory;
use Modules\Product\Application\Category\DeleteCategory;
use Modules\Product\Application\Category\GetCategories;
use Modules\Product\Application\Category\GetCategory;
use Modules\Product\Application\Category\UpdateCategory;
use Modules\Product\Http\Requests\StoreCategoryRequest;
use Modules\Product\Http\Requests\UpdateCategoryRequest;

class ProductCategoryController extends Controller
{
    public function __construct(
        private readonly GetCategories $getCategories,
        private readonly GetCategory $getCategory,
        private readonly CreateCategory $createCategory,
        private readonly UpdateCategory $updateCategory,
        private readonly DeleteCategory $deleteCategory,
    ) {}

    public function index()
    {
        $filters = request()->only(['search', 'is_active']);
        $categories = $this->getCategories->execute($filters);

        return Inertia::render('Product/Categories/index', [
            'categories' => $categories,
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Product/Categories/create');
    }

    public function store(StoreCategoryRequest $request)
    {
        $this->createCategory->execute($request->validated());

        return redirect()->route('product.categories.index')
            ->with('success', 'Category created successfully.');
    }

    public function edit(int $id)
    {
        $category = $this->getCategory->execute($id);

        return Inertia::render('Product/Categories/edit', [
            'category' => $category,
        ]);
    }

    public function update(UpdateCategoryRequest $request, int $id)
    {
        $this->updateCategory->execute($id, $request->validated());

        return redirect()->route('product.categories.index')
            ->with('success', 'Category updated successfully.');
    }

    public function destroy(int $id)
    {
        $deleted = $this->deleteCategory->execute($id);

        if (! $deleted) {
            return redirect()->back()->with('error', 'Cannot delete category still used by products.');
        }

        return redirect()->back()->with('success', 'Category deleted successfully.');
    }
}
