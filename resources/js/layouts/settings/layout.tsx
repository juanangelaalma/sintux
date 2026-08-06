import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import AdminLayout from '@/layouts/admin/admin-layout';
import CompanyLayout from '@/layouts/company/company-layout';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    { title: 'Profile', href: edit(), icon: null },
    { title: 'Security', href: editSecurity(), icon: null },
    { title: 'Appearance', href: editAppearance(), icon: null },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage<any>().props;
    const isSuperadmin = auth?.user?.role === 'superadmin';

    const layout = (
        <div className="px-4 py-6">
            <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">Settings</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your profile and account settings</p>

            <div className="mt-6 flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1" aria-label="Settings">
                        {sidebarNavItems.map((item, index) => (
                            <Link
                                key={`${toUrl(item.href)}-${index}`}
                                href={item.href}
                                className={cn(
                                    'rounded-lg px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-white/5',
                                    { 'bg-gray-100 dark:bg-white/5': isCurrentOrParentUrl(item.href) }
                                )}
                            >
                                {item.title}
                            </Link>
                        ))}
                    </nav>
                </aside>

                <div className="my-6 border-t border-gray-200 dark:border-gray-800 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">{children}</section>
                </div>
            </div>
        </div>
    );

    return isSuperadmin ? (
        <AdminLayout>{layout}</AdminLayout>
    ) : (
        <CompanyLayout>{layout}</CompanyLayout>
    );
}
