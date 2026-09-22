import { usePage } from '@inertiajs/react';
import React, { useState } from 'react';
import { SidebarProvider, useSidebar } from '@/context/SidebarContext';
import { LAYOUT_MARGIN } from '@/layouts/shared/sidebar-layout';
import { isActivePrefix } from '@/lib/navigation';
import SecondarySidebar from '../settings/secondary-sidebar';
import { SETTINGS_PREFIXES } from '../settings/settings-navigation';
import AppHeader from '../shared/app-header';
import Backdrop from '../shared/backdrop';
import CompanySidebar from './company-sidebar';

const CompanyLayoutContent: React.FC<{ children: React.ReactNode }> = ({
    children,
}) => {
    const { isExpanded, isHovered, isMobileOpen } = useSidebar();
    const { url } = usePage();

    const isSettingsContext = SETTINGS_PREFIXES.some((prefix) =>
        isActivePrefix(url, prefix),
    );

    // Override manual hanya berlaku untuk url saat dibuat: pindah halaman
    // kembali ikut konteks tanpa effect. Masuk area pengaturan → buka
    // (dengan animasi), keluar area → tutup.
    const [secondaryOverride, setSecondaryOverride] = useState<{
        open: boolean;
        url: string;
    } | null>(null);

    const secondaryOpen =
        secondaryOverride && secondaryOverride.url === url
            ? secondaryOverride.open
            : isSettingsContext;

    const showSecondary = isSettingsContext && secondaryOpen;

    const getMarginLeft = () => {
        if (isMobileOpen) {
            return 'ml-0';
        }

        if (showSecondary) {
            return isExpanded || isHovered
                ? LAYOUT_MARGIN.settingsExpanded
                : LAYOUT_MARGIN.settingsCollapsed;
        }

        return isExpanded || isHovered
            ? LAYOUT_MARGIN.expanded
            : LAYOUT_MARGIN.collapsed;
    };

    return (
        <div className="min-h-screen xl:flex">
            <div>
                <CompanySidebar
                    isSettingsContext={isSettingsContext}
                    secondaryOpen={secondaryOpen}
                    setSecondaryOpen={(open) =>
                        setSecondaryOverride({ open, url })
                    }
                />
                {isSettingsContext && <SecondarySidebar open={secondaryOpen} />}
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
