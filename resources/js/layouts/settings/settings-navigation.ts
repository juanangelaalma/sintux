import taxesRoute from '@/routes/accounting/taxes';
import rulesRoute from '@/routes/approval/rules';
import branchesRoute from '@/routes/company/branches';
import usersRoute from '@/routes/company/users';
import productRoute from '@/routes/product';
import profileRoute from '@/routes/profile';
import ordersRoute from '@/routes/purchasing/orders';
import type { SettingsNavItem } from '@/types/navigation';

export const SETTINGS_NAV_ITEMS: SettingsNavItem[] = [
    {
        name: 'Perusahaan',
        path: branchesRoute.index.url(),
        permission: 'company.branch.manage',
    },
    {
        name: 'Pengaturan Pengguna',
        path: usersRoute.index.url(),
        permission: 'company.user.manage',
    },
    {
        name: 'Pembelian',
        path: ordersRoute.index.url(),
        permission: 'purchasing.po.view',
    },
    {
        name: 'Produk',
        path: productRoute.hub.url(),
        permission: 'product.view',
    },
    {
        name: 'Aturan Approval',
        path: rulesRoute.index.url(),
        permission: 'approval.rule.view',
    },
    {
        name: 'Pajak',
        path: taxesRoute.index.url(),
        permission: 'accounting.tax.view',
    },
    {
        name: 'Profil & Akun',
        path: profileRoute.edit.url(),
    },
];

// Prefix yang mengaktifkan konteks pengaturan (double sidebar).
// Dipertahankan sama seperti sebelumnya: '/product' dan '/purchasing/orders'
// punya item di secondary tapi tidak memicu konteks ini.
export const SETTINGS_PREFIXES: string[] = [
    '/settings',
    usersRoute.index.url(),
    branchesRoute.index.url(),
    rulesRoute.index.url(),
    taxesRoute.index.url(),
];
