import { Link } from '@inertiajs/react';
import { useSidebar } from '@/context/SidebarContext';
import { HorizontaLDots } from '@/icons';

type SidebarShellProps = {
    asideAriaLabel: string;
    navAriaLabel: string;
    menuTitle: string;
    logoHref: string;
    logoAlt?: string;
    children: React.ReactNode;
};

const SidebarShell: React.FC<SidebarShellProps> = ({
    asideAriaLabel,
    navAriaLabel,
    menuTitle,
    logoHref,
    logoAlt = 'Sintux',
    children,
}) => {
    const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
    const showLabel = isExpanded || isHovered || isMobileOpen;

    return (
        <aside
            aria-label={asideAriaLabel}
            className={`fixed top-0 left-0 z-50 mt-16 flex h-screen flex-col border-r border-gray-200 bg-white px-5 text-gray-900 transition-all duration-300 ease-in-out lg:mt-0 dark:border-gray-800 dark:bg-gray-900 ${
                isExpanded || isMobileOpen
                    ? 'w-[290px]'
                    : isHovered
                      ? 'w-[290px]'
                      : 'w-[90px]'
            } ${isMobileOpen ? 'translate-x-0' : '-translate-x-full'} lg:translate-x-0`}
            onMouseEnter={() => !isExpanded && setIsHovered(true)}
            onMouseLeave={() => setIsHovered(false)}
        >
            <div
                className={`flex py-8 ${
                    !isExpanded && !isHovered
                        ? 'lg:justify-center'
                        : 'justify-start'
                }`}
            >
                <Link href={logoHref} aria-label={logoAlt}>
                    {showLabel ? (
                        <>
                            <img
                                className="dark:hidden"
                                src="/images/logo/logo.svg"
                                alt={logoAlt}
                                width={150}
                                height={40}
                            />
                            <img
                                className="hidden dark:block"
                                src="/images/logo/logo-dark.svg"
                                alt={logoAlt}
                                width={150}
                                height={40}
                            />
                        </>
                    ) : (
                        <img
                            src="/images/logo/logo-icon.svg"
                            alt={logoAlt}
                            width={32}
                            height={32}
                        />
                    )}
                </Link>
            </div>
            <div className="no-scrollbar flex flex-col overflow-y-auto duration-300 ease-linear">
                <nav className="mb-6" aria-label={navAriaLabel}>
                    <div className="flex flex-col gap-4">
                        <div>
                            <h2
                                className={`mb-4 flex text-xs leading-[20px] text-gray-400 uppercase ${
                                    !isExpanded && !isHovered
                                        ? 'lg:justify-center'
                                        : 'justify-start'
                                }`}
                            >
                                {showLabel ? (
                                    menuTitle
                                ) : (
                                    <HorizontaLDots
                                        className="size-6"
                                        aria-hidden="true"
                                    />
                                )}
                            </h2>
                            {children}
                        </div>
                    </div>
                </nav>
            </div>
        </aside>
    );
};

export default SidebarShell;
