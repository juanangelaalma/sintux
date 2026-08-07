<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    /**
     * Switch the active branch context for the authenticated member.
     */
    public function switchBranch(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
        ]);

        $membership = auth()->user()?->companyUserFor((string) tenant('id'));

        abort_unless($membership && $membership->branch_id, 403);

        $isHq = DB::table('branches')
            ->where('id', $membership->branch_id)
            ->value('is_headquarters');

        abort_if($isHq, 403);

        abort_unless(in_array((int) $validated['branch_id'], $membership->allowedBranchIds(), true), 403);

        session(['active_branch_id' => (int) $validated['branch_id']]);

        return redirect()->back();
    }
}
