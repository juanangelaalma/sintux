import { usePage } from '@inertiajs/react';
import React from 'react';
import { SidebarProvider, useSidebar } from '@/context/SidebarContext';
import SecondarySidebar from '../settings/secondary-sidebar';
import AppHeader from '../shared/app-header';
import Backdrop from '../shared/backdrop';
import CompanySidebar from './company-sidebar';

const settingsPrefixes = [
    '/settings',
    '/company/users',
    '/company/branches',
    '/approval/rules',
];

const CompanyLayoutContent: React.FC<{ children: React.ReactNode }> = ({
    children,
}) => {
    const { isExpanded, isHovered, isMobileOpen } = useSidebar();
    const { url } = usePage();

    const isSettingsContext = settingsPrefixes.some((prefix) =>
        url.startsWith(prefix),
    );

    const getMarginLeft = () => {
        if (isMobileOpen) return 'ml-0';
        if (isSettingsContext) {
            return isExpanded || isHovered ? 'lg:ml-[530px]' : 'lg:ml-[330px]';
        }
        return isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]';
    };

    return (
        <div className="min-h-screen xl:flex">
            <div>
                <CompanySidebar />
                {isSettingsContext && <SecondarySidebar />}
                <Backdrop />
            </div>
            <div
                className={`flex-1 transition-all duration-300 ease-in-out ${getMarginLeft()}`}
            >
                <AppHeader />
                <div className="mx-auto max-w-(--breakpoint-2xl) p-4 md:p-6">
                    {children}
                </div>
            </div>
        </div>
    );
};

export default function CompanyLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    return (
        <SidebarProvider>
            <CompanyLayoutContent>{children}</CompanyLayoutContent>
        </SidebarProvider>
    );
}
