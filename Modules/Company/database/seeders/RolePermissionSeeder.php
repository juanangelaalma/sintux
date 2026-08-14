<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Company\Models\Permission;
use Modules\Company\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the global role & permission catalog.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'Owner', 'slug' => 'owner', 'level' => 'company', 'is_system' => true],
            ['name' => 'Admin', 'slug' => 'admin', 'level' => 'company', 'is_system' => true],
            ['name' => 'Member', 'slug' => 'member', 'level' => 'company', 'is_system' => true],
            ['name' => 'Sales Admin', 'slug' => 'sales_admin', 'level' => 'branch'],
            ['name' => 'Warehouse Admin', 'slug' => 'warehouse_admin', 'level' => 'branch'],
            ['name' => 'Finance', 'slug' => 'finance', 'level' => 'branch'],
            ['name' => 'Fiscal', 'slug' => 'fiscal', 'level' => 'branch'],
            ['name' => 'Accounting', 'slug' => 'accounting', 'level' => 'branch'],
            ['name' => 'Cashier', 'slug' => 'cashier', 'level' => 'branch'],
            ['name' => 'Inventory Staff', 'slug' => 'inventory_staff', 'level' => 'branch'],
        ];

        $permissions = [
            ['name' => 'View Dashboard', 'slug' => 'dashboard.view', 'module' => 'core'],
            ['name' => 'Manage Company Users', 'slug' => 'company.user.manage', 'module' => 'company'],
            ['name' => 'Manage Company Branches', 'slug' => 'company.branch.manage', 'module' => 'company'],
            ['name' => 'Manage Company Settings', 'slug' => 'company.settings.manage', 'module' => 'company'],
            ['name' => 'View Products', 'slug' => 'product.view', 'module' => 'product'],
            ['name' => 'Create Products', 'slug' => 'product.create', 'module' => 'product'],
            ['name' => 'Update Products', 'slug' => 'product.update', 'module' => 'product'],
            ['name' => 'Delete Products', 'slug' => 'product.delete', 'module' => 'product'],
            ['name' => 'View Contacts', 'slug' => 'contact.view', 'module' => 'contact'],
            ['name' => 'Create Contacts', 'slug' => 'contact.create', 'module' => 'contact'],
            ['name' => 'Update Contacts', 'slug' => 'contact.update', 'module' => 'contact'],
            ['name' => 'Delete Contacts', 'slug' => 'contact.delete', 'module' => 'contact'],
            ['name' => 'View Warehouses', 'slug' => 'warehouse.view', 'module' => 'warehouse'],
            ['name' => 'Create Warehouses', 'slug' => 'warehouse.create', 'module' => 'warehouse'],
            ['name' => 'Update Warehouses', 'slug' => 'warehouse.update', 'module' => 'warehouse'],
            ['name' => 'Delete Warehouses', 'slug' => 'warehouse.delete', 'module' => 'warehouse'],
            ['name' => 'View Warehouse Stock', 'slug' => 'warehouse.stock.view', 'module' => 'warehouse'],
            ['name' => 'Manage Warehouse Stock', 'slug' => 'warehouse.stock.manage', 'module' => 'warehouse'],
            ['name' => 'Request Warehouse Stock', 'slug' => 'warehouse.stock.request', 'module' => 'warehouse'],
            ['name' => 'Approve Warehouse Stock Request', 'slug' => 'warehouse.stock.request.approve', 'module' => 'warehouse'],
            ['name' => 'Transfer Warehouse Stock', 'slug' => 'warehouse.stock.transfer', 'module' => 'warehouse'],
            ['name' => 'View Finance Transactions', 'slug' => 'finance.transaction.view', 'module' => 'finance'],
            ['name' => 'Manage Finance Transactions', 'slug' => 'finance.transaction.manage', 'module' => 'finance'],
            ['name' => 'View Fiscal Reports', 'slug' => 'fiscal.report.view', 'module' => 'fiscal'],
            ['name' => 'View Chart of Accounts', 'slug' => 'accounting.account.view', 'module' => 'accounting'],
            ['name' => 'Manage Chart of Accounts', 'slug' => 'accounting.account.manage', 'module' => 'accounting'],
            ['name' => 'View Accounting Journals', 'slug' => 'accounting.journal.view', 'module' => 'accounting'],
            ['name' => 'Manage Accounting Journals', 'slug' => 'accounting.journal.manage', 'module' => 'accounting'],
        ];

        $mapping = [
            'owner' => collect($permissions)->pluck('slug')->all(),
            'admin' => [
                'dashboard.view',
                'company.user.manage',
                'company.branch.manage',
                'company.settings.manage',
                'product.view',
                'product.create',
                'product.update',
                'product.delete',
                'warehouse.view',
                'warehouse.create',
                'warehouse.update',
                'warehouse.delete',
                'warehouse.stock.view',
                'warehouse.stock.request',
                'warehouse.stock.request.approve',
                'warehouse.stock.transfer',
                'contact.view',
                'contact.create',
                'contact.update',
                'contact.delete',
                'finance.transaction.view',
                'fiscal.report.view',
                'accounting.account.view',
                'accounting.account.manage',
                'accounting.journal.view',
            ],
            'member' => ['dashboard.view'],
            'sales_admin' => ['dashboard.view', 'product.view', 'contact.view', 'contact.create', 'contact.update', 'contact.delete'],
            'warehouse_admin' => ['dashboard.view', 'product.view', 'product.create', 'product.update', 'product.delete', 'warehouse.view', 'warehouse.create', 'warehouse.update', 'warehouse.delete', 'warehouse.stock.view', 'warehouse.stock.manage', 'warehouse.stock.request', 'warehouse.stock.request.approve', 'warehouse.stock.transfer', 'contact.view'],
            'finance' => ['dashboard.view', 'finance.transaction.view', 'finance.transaction.manage', 'fiscal.report.view', 'contact.view'],
            'fiscal' => ['dashboard.view', 'fiscal.report.view'],
            'accounting' => ['dashboard.view', 'accounting.account.view', 'accounting.account.manage', 'accounting.journal.view', 'accounting.journal.manage', 'finance.transaction.view', 'contact.view'],
            'cashier' => ['dashboard.view', 'sales.order.view', 'sales.order.create', 'contact.view', 'contact.create'],
            'inventory_staff' => ['dashboard.view', 'product.view', 'warehouse.view', 'warehouse.stock.view', 'warehouse.stock.request', 'contact.view'],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(['slug' => $role['slug']], $role);
        }

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        foreach ($mapping as $roleSlug => $permissionSlugs) {
            $role = Role::where('slug', $roleSlug)->first();
            $permissionIds = Permission::whereIn('slug', $permissionSlugs)->pluck('id');
            $role->permissions()->sync($permissionIds);
        }
    }
}
