<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
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
        if (request()->wantsJson()) {
            return response()->json($this->getCategories->all());
        }

        return redirect('/product?tab=master&sub=categories');
    }

    public function create()
    {
        return redirect('/product?tab=master&sub=categories');
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = $this->createCategory->execute($request->validated());

        if ($request->wantsJson()) {
            return response()->json($category, 201);
        }

        return redirect()->back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function edit(int $id)
    {
        return redirect('/product?tab=master&sub=categories');
    }

    public function update(UpdateCategoryRequest $request, int $id)
    {
        $category = $this->updateCategory->execute($id, $request->validated());

        if ($request->wantsJson()) {
            return response()->json($category);
        }

        return redirect()->back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(int $id)
    {
        $deleted = $this->deleteCategory->execute($id);
        if (! $deleted) {
            return redirect()->back()->with('error', 'Kategori tidak dapat dihapus karena masih digunakan produk.');
        }

        return redirect()->back()->with('success', 'Kategori berhasil dihapus.');
    }
}
