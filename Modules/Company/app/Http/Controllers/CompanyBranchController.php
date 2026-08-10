<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\Company\Access\CompanyAccess;
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
     *
     * Payload: { scope: 'all' } or { scope: 'branch', branch_id } / { branch_id }.
     */
    public function switchBranch(Request $request)
    {
        $user = $request->user();
        $tenantId = (string) tenant('id');

        abort_unless($user && $user->companyUserFor($tenantId), 403);

        $validated = $request->validate([
            'scope' => ['nullable', Rule::in(['all', 'branch'])],
            'branch_id' => ['nullable', 'integer'],
        ]);

        $scope = $validated['scope'] ?? null;
        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($scope === 'all' && ! $branchId) {
            session(['branch_scope' => 'all']);
            session()->forget('active_branch_id');

            return redirect()->back();
        }

        abort_unless($branchId !== null, 422);

        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);
        abort_unless(in_array($branchId, $accessibleBranchIds, true), 403);

        $isHq = (bool) DB::table('branches')
            ->where('id', $branchId)
            ->value('is_headquarters');

        session([
            'active_branch_id' => $branchId,
            'branch_scope' => ($scope === 'all' || $isHq) ? 'all' : 'branch',
        ]);

        return redirect()->back();
    }
}
