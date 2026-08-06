<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Inertia\Inertia;
use Modules\Company\Application\CreateCompany;
use Modules\Company\Application\Exceptions\CompanyCreationFailed;
use Modules\Company\Http\Requests\StoreCompanyRequest;
use Modules\Company\Http\Requests\UpdateCompanyRequest;

class CompanyController extends Controller
{
    public function __construct(private readonly CreateCompany $createCompany) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $companies = Tenant::orderBy('created_at', 'desc')->get();

        return Inertia::render('admin/companies/index', [
            'companies' => $companies,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCompanyRequest $request)
    {
        try {
            $this->createCompany->execute($request->validated());
        } catch (CompanyCreationFailed $e) {
            return back()->withErrors(['id' => $e->getMessage()]);
        }

        return redirect()->route('admin.companies.index')->with('success', 'Company and Admin created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCompanyRequest $request, string $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update($request->validated());

        return redirect()->route('admin.companies.index')->with('success', 'Company updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $tenant = Tenant::findOrFail($id);

        // Tenant deletion will automatically trigger DeleteDatabase (Drop Schema)
        // via Stancl Tenancy Event Listeners.
        $tenant->delete();

        return redirect()->route('admin.companies.index')->with('success', 'Company deleted successfully.');
    }
}
