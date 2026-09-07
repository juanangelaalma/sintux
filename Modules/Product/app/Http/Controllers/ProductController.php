<?php

namespace Modules\Product\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\TaxQuery;
use Modules\Company\Application\CompanyAccess;
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
use Modules\Product\Http\Requests\UploadProductImageRequest;

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
        private readonly ChartOfAccountQuery $chartOfAccountQuery,
        private readonly TaxQuery $taxQuery,
    ) {}

    public function index()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);

        $filters = request()->only(['search', 'category_id', 'product_type', 'is_active']);
        $products = $this->getProducts->execute($filters, $branchIds);

        return Inertia::render('Product/Products/index', [
            'products' => $products,
            'categories' => $this->getCategories->all(),
            'uoms' => $this->getUoms->all(),
            'filters' => $filters,
        ]);
    }

    public function create()
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);
        $activeBranchId = $branchIds[0] ?? null;

        $allProducts = $this->getProducts->execute(['is_active' => true], $branchIds);

        $chartOfAccounts = $this->chartOfAccountQuery->listChartOfAccounts();

        return Inertia::render('Product/Products/create', [
            'activeBranch' => collect(CompanyAccess::accessibleBranches($user, $tenantId))->firstWhere('id', $activeBranchId),
            'categories' => $this->getCategories->all(),
            'uoms' => $this->getUoms->all(),
            'availableProducts' => $allProducts['data'] ?? [],
            'chartOfAccounts' => $chartOfAccounts,
            'purchaseTaxes' => $this->taxQuery->listForPurchase(),
            'salesTaxes' => $this->taxQuery->listForSale(),
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
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);

        $product = $this->getProduct->execute($id);
        $variants = $this->getVariants->execute($id);
        $allProducts = $this->getProducts->execute(['is_active' => true], $branchIds);

        return Inertia::render('Product/Products/edit', [
            'product' => $product,
            'variants' => $variants,
            'categories' => $this->getCategories->all(false),
            'uoms' => $this->getUoms->all(false),
            'availableProducts' => array_values(array_filter(
                $allProducts['data'] ?? [],
                fn ($p) => $p['id'] !== $id
            )),
            'purchaseTaxes' => $this->taxQuery->listForPurchase(),
            'salesTaxes' => $this->taxQuery->listForSale(),
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

    /**
     * Securely handle product image upload.
     */
    public function uploadImage(UploadProductImageRequest $request)
    {
        $file = $request->file('image');

        // Store file securely with hashed unique filename in 'public/products/images'
        $path = $file->store('products/images', 'public');
        $url = Storage::url($path);

        return response()->json([
            'success' => true,
            'path' => $path,
            'url' => $url,
        ]);
    }
}
