import React from 'react';
import AppHeader from '../shared/app-header';
import CompanySidebar from './company-sidebar';
import Backdrop from '../shared/backdrop';
import { SidebarProvider, useSidebar } from '@/context/SidebarContext';

const CompanyLayoutContent: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isExpanded, isHovered, isMobileOpen } = useSidebar();

  return (
    <div className="min-h-screen xl:flex">
      <div>
        <CompanySidebar />
        <Backdrop />
      </div>
      <div
        className={`flex-1 transition-all duration-300 ease-in-out ${
          isExpanded || isHovered ? 'lg:ml-[290px]' : 'lg:ml-[90px]'
        } ${isMobileOpen ? 'ml-0' : ''}`}
      >
        <AppHeader />
        <div className="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
          {children}
        </div>
      </div>
    </div>
  );
};

export default function CompanyLayout({ children }: { children: React.ReactNode }) {
  return (
    <SidebarProvider>
      <CompanyLayoutContent>{children}</CompanyLayoutContent>
    </SidebarProvider>
  );
}
