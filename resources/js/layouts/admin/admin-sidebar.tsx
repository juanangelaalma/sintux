import { Link, usePage } from '@inertiajs/react';
import { useCallback } from 'react';
import { useSidebar } from '@/context/SidebarContext';
import { GridIcon, UserCircleIcon, GroupIcon } from '@/icons';
import SidebarShell from '@/layouts/shared/sidebar-shell';
import { isActivePrefix } from '@/lib/navigation';
import adminRoute from '@/routes/admin';
import companiesRoute from '@/routes/admin/companies';
import rolesRoute from '@/routes/admin/roles';
import type { SidebarEntry } from '@/types/navigation';

const adminNavItems: SidebarEntry[] = [
    {
        icon: <GridIcon />,
        name: 'Platform Overview',
        path: adminRoute.dashboard.url(),
    },
    {
        icon: <UserCircleIcon />,
        name: 'Tenants / Companies',
        path: companiesRoute.index.url(),
    },
    {
        icon: <GroupIcon />,
        name: 'Role Permissions',
        path: rolesRoute.index.url(),
    },
];

const AdminSidebar: React.FC = () => {
    const { isExpanded, isMobileOpen, isHovered } = useSidebar();
    const { url } = usePage();

    const isActive = useCallback(
        (path?: string) => isActivePrefix(url, path),
        [url],
    );
    const isCollapsed = !isExpanded && !isHovered && !isMobileOpen;

    return (
        <SidebarShell
            asideAriaLabel="Navigasi admin"
            navAriaLabel="Menu admin"
            menuTitle="Admin Menu"
            logoHref={adminRoute.dashboard.url()}
        >
            <ul className="flex flex-col gap-4">
                {adminNavItems.map((nav) => (
                    <li key={nav.name}>
                        <Link
                            href={nav.path}
                            aria-label={nav.name}
                            title={isCollapsed ? nav.name : undefined}
                            aria-current={
                                isActive(nav.path) ? 'page' : undefined
                            }
                            className={`group menu-item ${
                                isActive(nav.path)
                                    ? 'menu-item-active'
                                    : 'menu-item-inactive'
                            }`}
                        >
                            <span
                                aria-hidden="true"
                                className={`menu-item-icon-size ${
                                    isActive(nav.path)
                                        ? 'menu-item-icon-active'
                                        : 'menu-item-icon-inactive'
                                }`}
                            >
                                {nav.icon}
                            </span>
                            {(isExpanded || isHovered || isMobileOpen) && (
                                <span className="menu-item-text">
                                    {nav.name}
                                </span>
                            )}
                        </Link>
                    </li>
                ))}
            </ul>
        </SidebarShell>
    );
};

export default AdminSidebar;
