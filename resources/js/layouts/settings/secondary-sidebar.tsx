import { Link, usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { useSidebar } from '@/context/SidebarContext';
import { cn } from '@/lib/utils';

type SubItem = {
    name: string;
    path: string;
    permission?: string;
    subItems?: Array<{ name: string; path: string; permission?: string }>;
};

export default function SecondarySidebar() {
    const { url, props } = usePage();
    const { isExpanded, isHovered } = useSidebar();
    const auth = (props.auth ?? {}) as { permissions?: string[] };
    const perms = auth.permissions ?? [];

    const navItems: SubItem[] = [
        {
            name: 'Perusahaan',
            path: '/company/branches',
            permission: 'company.branch.manage',
        },
        {
            name: 'Pengaturan Pengguna',
            path: '/company/users',
            permission: 'company.user.manage',
        },
        {
            name: 'Pembelian',
            path: '/purchasing/orders',
            permission: 'purchasing.po.view',
        },
        {
            name: 'Produk',
            path: '/product',
            permission: 'product.view',
        },
        {
            name: 'Aturan Approval',
            path: '/approval/rules',
            permission: 'approval.rule.view',
        },
        {
            name: 'Profil & Akun',
            path: '/settings/profile',
        },
    ];

    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>({});

    const toggleGroup = (name: string) => {
        setOpenGroups((prev) => ({
            ...prev,
            [name]: !prev[name],
        }));
    };

    const isActive = (path: string) => url.startsWith(path);

    const leftPosition =
        isExpanded || isHovered ? 'left-[290px]' : 'left-[90px]';

    return (
        <aside
            className={cn(
                'fixed top-0 z-40 hidden h-screen w-[240px] border-r border-gray-200 bg-[#f8fafc] pt-16 transition-all duration-300 ease-in-out lg:block lg:pt-0 dark:border-gray-800 dark:bg-gray-900',
                leftPosition,
            )}
        >
            <div className="flex h-full flex-col overflow-y-auto px-4 py-6">
                <div className="mb-5 flex items-center justify-between px-2">
                    <h2 className="text-xs font-bold tracking-wider text-indigo-600 uppercase dark:text-indigo-400">
                        PENGATURAN
                    </h2>
                </div>

                <div className="flex flex-col gap-1">
                    {navItems.map((item) => {
                        if (
                            item.permission &&
                            !perms.includes(item.permission)
                        ) {
                            return null;
                        }

                        const active = isActive(item.path);
                        const hasSub = Boolean(
                            item.subItems && item.subItems.length > 0,
                        );
                        const isOpen = openGroups[item.name] ?? false;

                        if (hasSub) {
                            return (
                                <div
                                    key={item.name}
                                    className="flex flex-col gap-1"
                                >
                                    <button
                                        type="button"
                                        onClick={() => toggleGroup(item.name)}
                                        className={cn(
                                            'flex w-full items-center justify-between rounded-lg px-3.5 py-2 text-sm font-medium transition-colors',
                                            active
                                                ? 'bg-indigo-100/80 font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                                : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                        )}
                                    >
                                        <span>{item.name}</span>
                                        <svg
                                            className={cn(
                                                'size-4 text-gray-500 transition-transform duration-200',
                                                isOpen ? 'rotate-180' : '',
                                            )}
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

                                    {isOpen && item.subItems && (
                                        <ul className="ml-3 flex flex-col gap-0.5 border-l border-gray-200 pl-4 dark:border-gray-800">
                                            {item.subItems.map((sub) => {
                                                const subActive = isActive(
                                                    sub.path,
                                                );
                                                return (
                                                    <li key={sub.name}>
                                                        <Link
                                                            href={sub.path}
                                                            className={cn(
                                                                'block rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                                                subActive
                                                                    ? 'bg-indigo-100/80 font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white',
                                                            )}
                                                        >
                                                            {sub.name}
                                                        </Link>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    )}
                                </div>
                            );
                        }

                        return (
                            <Link
                                key={item.name}
                                href={item.path}
                                className={cn(
                                    'block rounded-lg px-3.5 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-indigo-100/80 font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                )}
                            >
                                {item.name}
                            </Link>
                        );
                    })}
                </div>
            </div>
        </aside>
    );
}
