import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useSidebar } from '@/context/SidebarContext';
import { SIDEBAR_OFFSET } from '@/layouts/shared/sidebar-layout';
import { isActivePrefix } from '@/lib/navigation';
import { cn } from '@/lib/utils';
import { SETTINGS_NAV_ITEMS } from './settings-navigation';

export default function SecondarySidebar({ open }: { open: boolean }) {
    const { url, props } = usePage();
    const { isExpanded, isHovered, isMobileOpen, toggleMobileSidebar } =
        useSidebar();
    const auth = (props.auth ?? {}) as { permissions?: string[] };
    const perms = auth.permissions ?? [];

    const navItems = SETTINGS_NAV_ITEMS;

    // Dua fase agar mount ikut beranimasi: render pertama tersembunyi,
    // frame berikutnya baru terlihat sehingga transisi fade/slide berjalan.
    const [entered, setEntered] = useState(false);

    useEffect(() => {
        const frame = requestAnimationFrame(() => setEntered(true));

        return () => cancelAnimationFrame(frame);
    }, []);

    const shown = open && entered;

    const isActive = (path: string) => isActivePrefix(url, path);
    const leftPosition =
        isExpanded || isHovered
            ? SIDEBAR_OFFSET.expanded
            : SIDEBAR_OFFSET.collapsed;
    const visibleItems = navItems.filter(
        (item) => !item.permission || perms.includes(item.permission),
    );

    return (
        <aside
            aria-label="Navigasi pengaturan"
            className={cn(
                'fixed top-0 left-0 z-50 h-screen w-[240px] border-r border-gray-200 bg-[#f8fafc] pt-16 transition-all duration-300 ease-in-out lg:z-40 lg:pt-0 dark:border-gray-800 dark:bg-gray-900',
                leftPosition,
                isMobileOpen ? 'translate-x-0' : '-translate-x-full',
                // Desktop: meluncur dari balik main sidebar (kiri ke kanan)
                // dengan fade. Tertutup = terselip tak terlihat & tak
                // terjangkau keyboard (lg:invisible).
                shown
                    ? 'lg:translate-x-0 lg:opacity-100'
                    : 'lg:invisible lg:-translate-x-6 lg:opacity-0',
            )}
        >
            <nav
                aria-label="Menu pengaturan"
                className="flex h-full flex-col overflow-y-auto px-4 py-6"
            >
                <div className="mb-5 flex items-center justify-between px-2">
                    <h2 className="text-xs font-bold tracking-wider text-brand-600 uppercase dark:text-brand-400">
                        PENGATURAN
                    </h2>
                    <button
                        type="button"
                        onClick={toggleMobileSidebar}
                        aria-label="Tutup navigasi pengaturan"
                        className="rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 lg:hidden dark:text-gray-400 dark:hover:bg-gray-800"
                    >
                        <svg
                            aria-hidden="true"
                            className="size-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                {visibleItems.length === 0 ? (
                    <p
                        role="status"
                        className="rounded-lg bg-gray-100 px-3.5 py-3 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Tidak ada menu pengaturan untuk peran Anda. Hubungi
                        admin jika ini keliru.
                    </p>
                ) : null}

                <div className="flex flex-col gap-1">
                    {visibleItems.map((item) => {
                        const active = isActive(item.path);

                        return (
                            <Link
                                key={item.name}
                                href={item.path}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'block rounded-lg px-3.5 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-brand-50 font-semibold text-brand-600 dark:bg-brand-500/[0.12] dark:text-brand-300'
                                        : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800',
                                )}
                            >
                                {item.name}
                            </Link>
                        );
                    })}
                </div>
            </nav>
        </aside>
    );
}
