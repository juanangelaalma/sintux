import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
};

export type SidebarLeafItem = {
    name: string;
    path: string;
    permission?: string;
};

export type SidebarSubGroup = {
    name: string;
    permission?: string;
    subItems: SidebarLeafItem[];
};

export type SidebarChildItem = SidebarLeafItem | SidebarSubGroup;

export type SidebarEntry = {
    name: string;
    icon: ReactNode;
    path?: string;
    permission?: string;
    hqOnly?: boolean;
    nonHqOnly?: boolean;
    subItems?: SidebarChildItem[];
    /** Item ini membuka/menutup secondary sidebar (khusus menu Pengaturan). */
    togglesSecondary?: boolean;
};

export type SettingsNavItem = {
    name: string;
    path: string;
    permission?: string;
};
