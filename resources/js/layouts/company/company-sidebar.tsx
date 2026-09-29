import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Inbox,
    LayoutDashboard,
    ClipboardList,
    Package,
    PackageCheck,
    ReceiptText,
    Settings,
    ShoppingCart,
    Users,
} from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { useSidebar } from '@/context/SidebarContext';
import SidebarShell from '@/layouts/shared/sidebar-shell';
import { isActivePrefix } from '@/lib/navigation';
import { dashboard as dashboardRoute } from '@/routes';
import chartOfAccountsRoute from '@/routes/accounting/chart-of-accounts';
import inboxRoute from '@/routes/approval/inbox';
import branchesRoute from '@/routes/company/branches';
import contactsRoute from '@/routes/company/contacts';
import productRoute from '@/routes/product';
import grnsRoute from '@/routes/purchasing/grns';
import purchaseInvoicesRoute from '@/routes/purchasing/invoices';
import salesInvoicesRoute from '@/routes/sales/invoices';
import stockTransfersRoute from '@/routes/warehouse/stock-transfers';
import type {
    SidebarChildItem,
    SidebarEntry,
    SidebarSubGroup,
} from '@/types/navigation';

const COMPANY_NAV_ITEMS: SidebarEntry[] = [
    {
        icon: <LayoutDashboard />,
        name: 'Dashboard',
        path: dashboardRoute.url(),
    },
    {
        icon: <Users />,
        name: 'Kontak',
        subItems: [
            { name: 'Customer', path: contactsRoute.index.url('customers') },
            { name: 'Supplier', path: contactsRoute.index.url('suppliers') },
            { name: 'Employee', path: contactsRoute.index.url('employees') },
        ],
    },
    {
        icon: <ClipboardList />,
        name: 'Daftar Akun',
        path: chartOfAccountsRoute.index.url(),
        permission: 'accounting.account.view',
    },
    {
        icon: <Package />,
        name: 'Produk',
        path: productRoute.hub.url(),
        permission: 'product.view',
    },
    {
        icon: <ShoppingCart />,
        name: 'Pembelian',
        path: purchaseInvoicesRoute.index.url(),
        hqOnly: true,
    },
    {
        icon: <ReceiptText />,
        name: 'Penjualan',
        path: salesInvoicesRoute.index.url(),
    },
    {
        icon: <PackageCheck />,
        name: 'GRN',
        path: grnsRoute.index.url(),
        nonHqOnly: true,
    },
    {
        icon: <ArrowLeftRight />,
        name: 'Transfer Stok',
        path: stockTransfersRoute.index.url(),
        nonHqOnly: true,
    },
    {
        icon: <Inbox />,
        name: 'Persetujuan',
        path: inboxRoute.index.url(),
    },
    {
        icon: <Settings />,
        name: 'Pengaturan',
        path: branchesRoute.index.url(),
        togglesSecondary: true,
    },
];

// Catatan: item tanpa `permission` tampil untuk semua user terautentikasi.
// Otorisasi wajib ditegakkan server-side (middleware/policy Laravel);
// filter di sini hanya untuk UX, bukan boundary keamanan.

function isGroupSubItem(sub: SidebarChildItem): sub is SidebarSubGroup {
    return (
        'subItems' in sub && Array.isArray((sub as SidebarSubGroup).subItems)
    );
}

function hasPermission(
    item: { permission?: string; subItems?: SidebarChildItem[] },
    perms: string[],
): boolean {
    if (item.subItems && item.subItems.length > 0) {
        return item.subItems.some((child) => hasPermission(child, perms));
    }

    return !item.permission || perms.includes(item.permission);
}

type CompanySidebarProps = {
    isSettingsContext: boolean;
    secondaryOpen: boolean;
    setSecondaryOpen: (open: boolean) => void;
};

const CompanySidebar: React.FC<CompanySidebarProps> = ({
    isSettingsContext,
    secondaryOpen,
    setSecondaryOpen,
}) => {
    const { isExpanded, isMobileOpen, isHovered } = useSidebar();
    const { url, props } = usePage();

    const auth = (props.auth ?? {}) as {
        permissions?: string[];
        is_hq?: boolean;
    };
    const perms = useMemo(() => auth.permissions ?? [], [auth.permissions]);
    const isHq = auth.is_hq ?? false;

    const visibleNavItems = useMemo(() => {
        return COMPANY_NAV_ITEMS.filter(
            (n) =>
                (!n.hqOnly || isHq) &&
                (!n.nonHqOnly || !isHq) &&
                hasPermission(n, perms),
        );
    }, [perms, isHq]);

    const [manualSubmenu, setManualSubmenu] = useState<
        | { state: 'open'; type: 'main'; index: number; url: string }
        | { state: 'closed'; url: string }
        | null
    >(null);

    const [subGroupsState, setSubGroupsState] = useState<{
        url: string;
        values: Record<string, boolean>;
    }>({ url, values: {} });

    // Override manual hanya berlaku untuk url saat dibuat.
    // Navigasi otomatis kembali ke turunan otomatis tanpa effect.
    // State 'closed' memungkinkan menu yang terbuka otomatis (karena
    // route aktif) untuk ditutup manual oleh user.

    const isActive = useCallback(
        (path?: string) => isActivePrefix(url, path),
        [url],
    );

    const autoOpenSubmenu = useMemo(() => {
        let matched: { type: 'main'; index: number } | null = null;
        visibleNavItems.forEach((nav, index) => {
            if (nav.subItems) {
                nav.subItems.forEach((subItem) => {
                    if (isGroupSubItem(subItem)) {
                        subItem.subItems.forEach((leaf) => {
                            if (isActive(leaf.path)) {
                                matched = { type: 'main', index };
                            }
                        });
                    } else if (isActive(subItem.path)) {
                        matched = { type: 'main', index };
                    }
                });
            }
        });

        return matched;
    }, [isActive, visibleNavItems]);

    const openSubmenu: { type: 'main'; index: number } | null =
        manualSubmenu && manualSubmenu.url === url
            ? manualSubmenu.state === 'open'
                ? { type: manualSubmenu.type, index: manualSubmenu.index }
                : null
            : autoOpenSubmenu;

    const handleSubmenuToggle = (index: number, menuType: 'main') => {
        const currentlyOpen =
            openSubmenu?.type === menuType && openSubmenu?.index === index;

        if (currentlyOpen) {
            setManualSubmenu({ state: 'closed', url });
        } else {
            setManualSubmenu({ state: 'open', type: menuType, index, url });
        }
    };

    const manualSubGroups =
        subGroupsState.url === url ? subGroupsState.values : {};

    const isSubGroupOpen = (groupKey: string, group: SidebarSubGroup) => {
        if (manualSubGroups[groupKey] !== undefined) {
            return manualSubGroups[groupKey];
        }

        return group.subItems.some((leaf) => isActive(leaf.path));
    };

    const toggleSubGroup = (groupKey: string, group: SidebarSubGroup) => {
        const currentState = isSubGroupOpen(groupKey, group);
        setSubGroupsState({
            url,
            values: {
                ...(subGroupsState.url === url ? subGroupsState.values : {}),
                [groupKey]: !currentState,
            },
        });
    };

    const renderSubItem = (
        sub: SidebarChildItem,
        parentName: string,
        permissions: string[],
    ) => {
        if (isGroupSubItem(sub)) {
            const visibleLeaves = sub.subItems.filter(
                (leaf) =>
                    !leaf.permission || permissions.includes(leaf.permission),
            );

            if (visibleLeaves.length === 0) {
                return null;
            }

            const groupKey = `${parentName}-${sub.name}`;
            const open = isSubGroupOpen(groupKey, sub);

            return (
                <li key={sub.name} className="flex flex-col gap-1">
                    <button
                        type="button"
                        onClick={() => toggleSubGroup(groupKey, sub)}
                        aria-expanded={open}
                        aria-controls={`subgroup-${parentName}-${sub.name}`}
                        className="flex w-full items-center justify-between py-1.5 text-xs font-semibold tracking-wider text-gray-500 uppercase hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    >
                        <span>{sub.name}</span>
                        <svg
                            aria-hidden="true"
                            className={`size-3.5 transition-transform duration-200 ${
                                open ? 'rotate-180' : ''
                            }`}
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>

                    {open && (
                        <ul
                            id={`subgroup-${parentName}-${sub.name}`}
                            className="flex flex-col gap-1 border-l border-gray-200 pl-3 dark:border-gray-800"
                        >
                            {visibleLeaves.map((leaf) => (
                                <li key={leaf.name}>
                                    <Link
                                        href={leaf.path}
                                        aria-current={
                                            isActive(leaf.path)
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`block py-1 text-sm font-medium transition-colors ${
                                            isActive(leaf.path)
                                                ? 'font-semibold text-brand-500'
                                                : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                                        }`}
                                    >
                                        {leaf.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            );
        }

        if (sub.permission && !permissions.includes(sub.permission)) {
            return null;
        }

        return (
            <li key={sub.name}>
                <Link
                    href={sub.path}
                    aria-current={isActive(sub.path) ? 'page' : undefined}
                    className={`block py-1 text-sm font-medium transition-colors ${
                        isActive(sub.path)
                            ? 'font-semibold text-brand-500'
                            : 'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                    }`}
                >
                    {sub.name}
                </Link>
            </li>
        );
    };

    const renderMenuItems = (
        items: SidebarEntry[],
        menuType: 'main',
        permissions: string[],
    ) => (
        <ul className="flex flex-col gap-4">
            {items.map((nav, index) => {
                const isCollapsed = !isExpanded && !isHovered && !isMobileOpen;
                const isOpen =
                    openSubmenu?.type === menuType &&
                    openSubmenu?.index === index;

                return (
                    <li key={nav.name}>
                        {nav.subItems ? (
                            <>
                                <button
                                    type="button"
                                    onClick={() =>
                                        handleSubmenuToggle(index, menuType)
                                    }
                                    aria-label={nav.name}
                                    title={isCollapsed ? nav.name : undefined}
                                    aria-expanded={isOpen}
                                    aria-controls={`company-submenu-${index}`}
                                    className={`group menu-item ${
                                        isOpen
                                            ? 'menu-item-active'
                                            : 'menu-item-inactive'
                                    } cursor-pointer ${
                                        !isExpanded && !isHovered
                                            ? 'lg:justify-center'
                                            : 'lg:justify-start'
                                    }`}
                                >
                                    <span
                                        aria-hidden="true"
                                        className={`menu-item-icon-size ${
                                            isOpen
                                                ? 'menu-item-icon-active'
                                                : 'menu-item-icon-inactive'
                                        }`}
                                    >
                                        {nav.icon}
                                    </span>
                                    {(isExpanded ||
                                        isHovered ||
                                        isMobileOpen) && (
                                        <>
                                            <span className="menu-item-text">
                                                {nav.name}
                                            </span>
                                            <svg
                                                aria-hidden="true"
                                                className={`ml-auto size-4 transition-transform duration-200 ${
                                                    isOpen ? 'rotate-180' : ''
                                                }`}
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    strokeLinecap="round"
                                                    strokeLinejoin="round"
                                                    strokeWidth={2}
                                                    d="M19 9l-7 7-7-7"
                                                />
                                            </svg>
                                        </>
                                    )}
                                </button>

                                {isOpen &&
                                    (isExpanded ||
                                        isHovered ||
                                        isMobileOpen) && (
                                        <ul
                                            id={`company-submenu-${index}`}
                                            className="mt-2 flex flex-col gap-2 pl-9"
                                        >
                                            {nav.subItems.map((sub) =>
                                                renderSubItem(
                                                    sub,
                                                    nav.name,
                                                    permissions,
                                                ),
                                            )}
                                        </ul>
                                    )}
                            </>
                        ) : (
                            nav.path && (
                                <Link
                                    href={nav.path}
                                    aria-label={nav.name}
                                    title={isCollapsed ? nav.name : undefined}
                                    aria-current={
                                        isActive(nav.path) ? 'page' : undefined
                                    }
                                    aria-expanded={
                                        nav.togglesSecondary
                                            ? secondaryOpen
                                            : undefined
                                    }
                                    onClick={
                                        nav.togglesSecondary
                                            ? (e: React.MouseEvent) => {
                                                  if (isSettingsContext) {
                                                      // Sudah di area pengaturan:
                                                      // jadikan toggle buka/tutup
                                                      // tanpa navigasi ulang.
                                                      e.preventDefault();
                                                      setSecondaryOpen(
                                                          !secondaryOpen,
                                                      );
                                                  } else {
                                                      // Dari luar: navigasi ke halaman
                                                      // pengaturan dan minta secondary
                                                      // langsung terbuka saat mendarat.
                                                      setSecondaryOpen(true);
                                                  }
                                              }
                                            : undefined
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
                                    {(isExpanded ||
                                        isHovered ||
                                        isMobileOpen) && (
                                        <span className="menu-item-text">
                                            {nav.name}
                                        </span>
                                    )}
                                </Link>
                            )
                        )}
                    </li>
                );
            })}
        </ul>
    );

    return (
        <SidebarShell
            asideAriaLabel="Navigasi perusahaan"
            navAriaLabel="Menu perusahaan"
            menuTitle="Company Menu"
            logoHref={dashboardRoute.url()}
        >
            {renderMenuItems(visibleNavItems, 'main', perms)}
        </SidebarShell>
    );
};

export default CompanySidebar;
