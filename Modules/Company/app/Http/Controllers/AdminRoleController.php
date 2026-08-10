<?php

namespace Modules\Company\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Modules\Company\Http\Requests\UpdateRolePermissionsRequest;
use Modules\Company\Models\Permission;
use Modules\Company\Models\Role;

class AdminRoleController extends Controller
{
    /**
     * Display the role & permission configuration page.
     */
    public function index()
    {
        return Inertia::render('admin/roles/index', [
            'roles' => Role::with('permissions')
                ->orderBy('level')
                ->orderBy('name')
                ->get(),
            'permissions' => Permission::orderBy('module')
                ->orderBy('slug')
                ->get(),
        ]);
    }

    /**
     * Update the permissions assigned to a role.
     */
    public function update(UpdateRolePermissionsRequest $request, Role $role)
    {
        $role->permissions()->sync($request->validated('permissions'));

        return redirect()->route('admin.roles.index')->with('success', 'Role permissions updated successfully.');
    }
}
