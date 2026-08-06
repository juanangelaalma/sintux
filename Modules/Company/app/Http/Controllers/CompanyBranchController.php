<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Company\Http\Requests\StoreCompanyBranchRequest;
use Modules\Company\Http\Requests\UpdateCompanyBranchRequest;
use Modules\Company\Models\Branch;

class CompanyBranchController extends Controller
{
    /**
     * Display a listing of the company's branches.
     */
    public function index()
    {
        abort_unless(auth()->user()->hasPermissionTo('company.branch.manage'), 403);

        return Inertia::render('Company/Branches/index', [
            'branches' => Branch::orderByDesc('is_headquarters')->orderBy('name')->get(),
        ]);
    }

    /**
     * Store a newly created branch in storage.
     */
    public function store(StoreCompanyBranchRequest $request)
    {
        Branch::create($request->validated());

        return redirect()->route('company.branches.index')->with('success', 'Branch created successfully.');
    }

    /**
     * Update the specified branch in storage.
     */
    public function update(UpdateCompanyBranchRequest $request, int $branch)
    {
        Branch::findOrFail($branch)->update($request->validated());

        return redirect()->route('company.branches.index')->with('success', 'Branch updated successfully.');
    }
}
