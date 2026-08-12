<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Company\Application\CompanyAccess;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Application\DeleteCompanyUser;
use Modules\Company\Application\GetCompanyUsers;
use Modules\Company\Application\UpdateCompanyUser;
use Modules\Company\Http\Requests\StoreCompanyUserRequest;
use Modules\Company\Http\Requests\UpdateCompanyUserRequest;
use Modules\Company\Models\Role;

class CompanyUserController extends Controller
{
    public function __construct(
        private readonly GetCompanyUsers $getCompanyUsers,
        private readonly CreateCompanyUser $createCompanyUser,
        private readonly UpdateCompanyUser $updateCompanyUser,
        private readonly DeleteCompanyUser $deleteCompanyUser,
    ) {}

    /**
     * Display a listing of the company's users.
     */
    public function index()
    {
        $tenantId = (string) tenant('id');

        return Inertia::render('Company/Users/index', [
            'members' => $this->getCompanyUsers->execute($tenantId),
            'branches' => DB::table('branches')
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_headquarters']),
            'roles' => Role::orderBy('level')->orderBy('name')->get(['id', 'name', 'slug', 'level']),
            'canManageUsers' => CompanyAccess::can(auth()->user(), $tenantId, 'company.user.manage'),
        ]);
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreCompanyUserRequest $request)
    {
        $this->createCompanyUser->execute((string) tenant('id'), $request->validated());

        return redirect()->route('company.users.index')->with('success', 'User created successfully.');
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateCompanyUserRequest $request, int $user)
    {
        $this->updateCompanyUser->execute($user, $request->validated());

        return redirect()->route('company.users.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(int $user)
    {
        abort_unless(CompanyAccess::can(auth()->user(), (string) tenant('id'), 'company.user.manage'), 403);

        $this->deleteCompanyUser->execute($user);

        return redirect()->route('company.users.index')->with('success', 'User removed successfully.');
    }
}
