# TailAdmin Template Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Integrate TailAdmin admin dashboard template into the Laravel + Inertia.js project, replacing shadcn/ui with TailAdmin's design system.

**Architecture:** Adapt template's react-router components to Inertia.js (`Link`, `usePage`). Replace shadcn/ui CSS variables with TailAdmin's brand color system. Keep Laravel project structure intact.

**Tech Stack:** Laravel, Inertia.js, React 19, Tailwind CSS v4, Vite, vite-plugin-svgr

## Global Constraints

- All `react-router` imports (`Link`, `useLocation`, `useNavigate`) become Inertia equivalents (`Link`, `usePage().url`, `router.visit`)
- `Outlet` becomes `children` prop
- Font: `Outfit` (replaces `Instrument Sans`)
- Icons: SVG files imported via `?react` suffix (vite-plugin-svgr)
- No demo pages — only structural/layout components

---

### Task 1: Install/Remove Dependencies

**Files:**
- Modify: `package.json`

**Interfaces:**
- Produces: `vite-plugin-svgr` available for Task 3

- [ ] **Step 1: Install vite-plugin-svgr**

```bash
cd /home/juanalma/projects/sintux
pnpm add -D vite-plugin-svgr
```

- [ ] **Step 2: Remove shadcn/ui dependencies**

```bash
pnpm remove @radix-ui/react-avatar @radix-ui/react-checkbox @radix-ui/react-collapsible @radix-ui/react-dialog @radix-ui/react-dropdown-menu @radix-ui/react-label @radix-ui/react-navigation-menu @radix-ui/react-select @radix-ui/react-separator @radix-ui/react-slot @radix-ui/react-toggle @radix-ui/react-toggle-group @radix-ui/react-tooltip class-variance-authority sonner tw-animate-css
```

- [ ] **Step 3: Verify build still works**

```bash
pnpm run types:check
```

Expected: May have errors from removed deps — that's OK, will fix in later tasks.

- [ ] **Step 4: Commit**

```bash
git add package.json pnpm-lock.yaml
git commit -m "chore: add vite-plugin-svgr, remove shadcn/ui dependencies"
```

---

### Task 2: Update Vite Config

**Files:**
- Modify: `vite.config.ts`

**Interfaces:**
- Produces: SVGR plugin active for Task 3 (SVG icon imports with `?react`)

- [ ] **Step 1: Add svgr plugin to vite.config.ts**

Replace `vite.config.ts` with:

```ts
import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import svgr from 'vite-plugin-svgr';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        svgr(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
```

- [ ] **Step 2: Commit**

```bash
git add vite.config.ts
git commit -m "feat: add vite-plugin-svgr to vite config"
```

---

### Task 3: Copy SVG Icons

**Files:**
- Create: `resources/js/icons/` (all SVG files + index.ts from template)

**Interfaces:**
- Produces: Icon exports used by `AppSidebar` (Task 8) and `AppHeader` (Task 9)

- [ ] **Step 1: Copy icon SVG files from template**

```bash
cp free-react-tailwind-admin-dashboard-main/src/icons/*.svg resources/js/icons/
```

- [ ] **Step 2: Create `resources/js/icons/index.ts`**

```ts
import { default as PlusIcon } from "./plus.svg?react";
import { default as CloseIcon } from "./close.svg?react";
import { default as BoxIcon } from "./box.svg?react";
import { default as CheckCircleIcon } from "./check-circle.svg?react";
import { default as AlertIcon } from "./alert.svg?react";
import { default as InfoIcon } from "./info.svg?react";
import { default as ErrorIcon } from "./info-error.svg?react";
import { default as BoltIcon } from "./bolt.svg?react";
import { default as ArrowUpIcon } from "./arrow-up.svg?react";
import { default as ArrowDownIcon } from "./arrow-down.svg?react";
import { default as FolderIcon } from "./folder.svg?react";
import { default as VideoIcon } from "./videos.svg?react";
import { default as AudioIcon } from "./audio.svg?react";
import { default as GridIcon } from "./grid.svg?react";
import { default as FileIcon } from "./file.svg?react";
import { default as DownloadIcon } from "./download.svg?react";
import { default as ArrowRightIcon } from "./arrow-right.svg?react";
import { default as GroupIcon } from "./group.svg?react";
import { default as BoxIconLine } from "./box-line.svg?react";
import { default as ShootingStarIcon } from "./shooting-star.svg?react";
import { default as DollarLineIcon } from "./dollar-line.svg?react";
import { default as TrashBinIcon } from "./trash.svg?react";
import { default as AngleUpIcon } from "./angle-up.svg?react";
import { default as AngleDownIcon } from "./angle-down.svg?react";
import { default as AngleLeftIcon } from "./angle-left.svg?react";
import { default as AngleRightIcon } from "./angle-right.svg?react";
import { default as PencilIcon } from "./pencil.svg?react";
import { default as CheckLineIcon } from "./check-line.svg?react";
import { default as CloseLineIcon } from "./close-line.svg?react";
import { default as ChevronDownIcon } from "./chevron-down.svg?react";
import { default as ChevronUpIcon } from "./chevron-up.svg?react";
import { default as PaperPlaneIcon } from "./paper-plane.svg?react";
import { default as LockIcon } from "./lock.svg?react";
import { default as EnvelopeIcon } from "./envelope.svg?react";
import { default as UserIcon } from "./user-line.svg?react";
import { default as CalenderIcon } from "./calender-line.svg?react";
import { default as EyeIcon } from "./eye.svg?react";
import { default as EyeCloseIcon } from "./eye-close.svg?react";
import { default as TimeIcon } from "./time.svg?react";
import { default as CopyIcon } from "./copy.svg?react";
import { default as ChevronLeftIcon } from "./chevron-left.svg?react";
import { default as UserCircleIcon } from "./user-circle.svg?react";
import { default as TaskIcon } from "./task-icon.svg?react";
import { default as ListIcon } from "./list.svg?react";
import { default as TableIcon } from "./table.svg?react";
import { default as PageIcon } from "./page.svg?react";
import { default as PieChartIcon } from "./pie-chart.svg?react";
import { default as BoxCubeIcon } from "./box-cube.svg?react";
import { default as PlugInIcon } from "./plug-in.svg?react";
import { default as DocsIcon } from "./docs.svg?react";
import { default as MailIcon } from "./mail-line.svg?react";
import { default as HorizontaLDots } from "./horizontal-dots.svg?react";
import { default as ChatIcon } from "./chat.svg?react";
import { default as MoreDotIcon } from "./moredot.svg?react";
import { default as AlertHexaIcon } from "./alert-hexa.svg?react";
import { default as ErrorHexaIcon } from "./info-hexa.svg?react";

export {
  ErrorHexaIcon,
  AlertHexaIcon,
  MoreDotIcon,
  DownloadIcon,
  FileIcon,
  GridIcon,
  AudioIcon,
  VideoIcon,
  BoltIcon,
  PlusIcon,
  BoxIcon,
  CloseIcon,
  CheckCircleIcon,
  AlertIcon,
  InfoIcon,
  ErrorIcon,
  ArrowUpIcon,
  FolderIcon,
  ArrowDownIcon,
  ArrowRightIcon,
  GroupIcon,
  BoxIconLine,
  ShootingStarIcon,
  DollarLineIcon,
  TrashBinIcon,
  AngleUpIcon,
  AngleDownIcon,
  PencilIcon,
  CheckLineIcon,
  CloseLineIcon,
  ChevronDownIcon,
  PaperPlaneIcon,
  EnvelopeIcon,
  LockIcon,
  UserIcon,
  CalenderIcon,
  EyeIcon,
  EyeCloseIcon,
  TimeIcon,
  CopyIcon,
  ChevronLeftIcon,
  UserCircleIcon,
  TaskIcon,
  ListIcon,
  TableIcon,
  PageIcon,
  PieChartIcon,
  BoxCubeIcon,
  PlugInIcon,
  DocsIcon,
  MailIcon,
  HorizontaLDots,
  ChevronUpIcon,
  ChatIcon,
  AngleLeftIcon,
  AngleRightIcon,
};
```

- [ ] **Step 3: Add SVG type declaration**

Create `resources/js/svg.d.ts`:

```ts
declare module '*.svg?react' {
  import type { ComponentType, SVGProps } from 'react';
  const content: ComponentType<SVGProps<SVGSVGElement>>;
  export default content;
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/icons/ resources/js/svg.d.ts
git commit -m "feat: add TailAdmin SVG icons with vite-plugin-svgr"
```

---

### Task 4: Replace CSS Theme

**Files:**
- Replace: `resources/css/app.css`

**Interfaces:**
- Produces: TailAdmin color tokens, custom utilities (`menu-item`, `menu-dropdown-item`, etc.) used by all layout components

- [ ] **Step 1: Replace `resources/css/app.css`**

Replace entire contents with the TailAdmin CSS from `free-react-tailwind-admin-dashboard-main/src/index.css`. The file is ~792 lines. Copy it verbatim, but remove the Google Fonts `@import` line (we'll load Outfit via Bunny fonts in vite config instead).

The key sections are:
- `@import "tailwindcss"`
- `@custom-variant dark (&:is(.dark *))`
- `@theme` block with all brand/gray/success/error/warning colors
- `@layer base` with body styles
- `@utility` blocks for menu-item, menu-dropdown-item, no-scrollbar, custom-scrollbar
- Third-party library CSS (apexcharts, flatpickr, fullcalendar) — keep for future use

- [ ] **Step 2: Update vite config to use Outfit font via Bunny**

Update the `laravel()` plugin in `vite.config.ts` to use `Outfit` instead of `Instrument Sans`:

```ts
fonts: [
    bunny('Outfit', {
        weights: [300, 400, 500, 600, 700],
    }),
],
```

- [ ] **Step 3: Commit**

```bash
git add resources/css/app.css vite.config.ts
git commit -m "feat: replace CSS theme with TailAdmin design system"
```

---

### Task 5: Create SidebarContext

**Files:**
- Create: `resources/js/context/SidebarContext.tsx`

**Interfaces:**
- Produces: `useSidebar()`, `SidebarProvider` used by `AppSidebar` (Task 8), `AppHeader` (Task 9), `Backdrop` (Task 7), `AppLayout` (Task 10)

- [ ] **Step 1: Create `resources/js/context/SidebarContext.tsx`**

Copy from `free-react-tailwind-admin-dashboard-main/src/context/SidebarContext.tsx` verbatim — no react-router adaptation needed (this file doesn't use react-router):

```tsx
import { createContext, useContext, useState, useEffect } from "react";

type SidebarContextType = {
  isExpanded: boolean;
  isMobileOpen: boolean;
  isHovered: boolean;
  activeItem: string | null;
  openSubmenu: string | null;
  toggleSidebar: () => void;
  toggleMobileSidebar: () => void;
  setIsHovered: (isHovered: boolean) => void;
  setActiveItem: (item: string | null) => void;
  toggleSubmenu: (item: string) => void;
};

const SidebarContext = createContext<SidebarContextType | undefined>(undefined);

export const useSidebar = () => {
  const context = useContext(SidebarContext);
  if (!context) {
    throw new Error("useSidebar must be used within a SidebarProvider");
  }
  return context;
};

export const SidebarProvider: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const [isExpanded, setIsExpanded] = useState(true);
  const [isMobileOpen, setIsMobileOpen] = useState(false);
  const [isMobile, setIsMobile] = useState(false);
  const [isHovered, setIsHovered] = useState(false);
  const [activeItem, setActiveItem] = useState<string | null>(null);
  const [openSubmenu, setOpenSubmenu] = useState<string | null>(null);

  useEffect(() => {
    const handleResize = () => {
      const mobile = window.innerWidth < 768;
      setIsMobile(mobile);
      if (!mobile) {
        setIsMobileOpen(false);
      }
    };

    handleResize();
    window.addEventListener("resize", handleResize);

    return () => {
      window.removeEventListener("resize", handleResize);
    };
  }, []);

  const toggleSidebar = () => {
    setIsExpanded((prev) => !prev);
  };

  const toggleMobileSidebar = () => {
    setIsMobileOpen((prev) => !prev);
  };

  const toggleSubmenu = (item: string) => {
    setOpenSubmenu((prev) => (prev === item ? null : item));
  };

  return (
    <SidebarContext.Provider
      value={{
        isExpanded: isMobile ? false : isExpanded,
        isMobileOpen,
        isHovered,
        activeItem,
        openSubmenu,
        toggleSidebar,
        toggleMobileSidebar,
        setIsHovered,
        setActiveItem,
        toggleSubmenu,
      }}
    >
      {children}
    </SidebarContext.Provider>
  );
};
```

- [ ] **Step 2: Commit**

```bash
mkdir -p resources/js/context
git add resources/js/context/SidebarContext.tsx
git commit -m "feat: add SidebarContext from TailAdmin"
```

---

### Task 6: Copy Static Assets (Logos)

**Files:**
- Copy: template's `public/images/` to project's `public/images/`

**Interfaces:**
- Produces: Logo images referenced by `AppSidebar` and `AppHeader`

- [ ] **Step 1: Copy logo images**

```bash
mkdir -p public/images/logo
cp free-react-tailwind-admin-dashboard-main/public/images/logo/*.svg public/images/logo/
```

- [ ] **Step 2: Verify files exist**

```bash
ls public/images/logo/
```

Expected: `logo.svg`, `logo-dark.svg`, `logo-icon.svg`

- [ ] **Step 3: Commit**

```bash
git add public/images/logo/
git commit -m "feat: add TailAdmin logo images"
```

---

### Task 7: Create Layout Support Components

**Files:**
- Create: `resources/js/components/ui/dropdown/Dropdown.tsx`
- Create: `resources/js/components/ui/dropdown/DropdownItem.tsx`
- Create: `resources/js/layouts/Backdrop.tsx`
- Create: `resources/js/layouts/SidebarWidget.tsx`

**Interfaces:**
- `Dropdown` consumed by `NotificationDropdown` (Task 9), `UserDropdown` (Task 9)
- `DropdownItem` consumed by `NotificationDropdown` (Task 9), `UserDropdown` (Task 9)
- `Backdrop` consumed by `AppLayout` (Task 10)
- `SidebarWidget` consumed by `AppSidebar` (Task 8)

- [ ] **Step 1: Create `resources/js/components/ui/dropdown/Dropdown.tsx`**

Copy from template verbatim (no react-router dependency):

```tsx
import type React from "react";
import { useEffect, useRef } from "react";

interface DropdownProps {
  isOpen: boolean;
  onClose: () => void;
  children: React.ReactNode;
  className?: string;
}

export const Dropdown: React.FC<DropdownProps> = ({
  isOpen,
  onClose,
  children,
  className = "",
}) => {
  const dropdownRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node) &&
        !(event.target as HTMLElement).closest(".dropdown-toggle")
      ) {
        onClose();
      }
    };

    document.addEventListener("mousedown", handleClickOutside);
    return () => {
      document.removeEventListener("mousedown", handleClickOutside);
    };
  }, [onClose]);

  if (!isOpen) return null;

  return (
    <div
      ref={dropdownRef}
      className={`absolute z-40 right-0 mt-2 rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark ${className}`}
    >
      {children}
    </div>
  );
};
```

- [ ] **Step 2: Create `resources/js/components/ui/dropdown/DropdownItem.tsx`**

Adapt from template — replace `react-router` `Link` with Inertia `Link`:

```tsx
import type React from "react";
import { Link } from "@inertiajs/react";

interface DropdownItemProps {
  tag?: "a" | "button";
  to?: string;
  onClick?: () => void;
  onItemClick?: () => void;
  baseClassName?: string;
  className?: string;
  children: React.ReactNode;
}

export const DropdownItem: React.FC<DropdownItemProps> = ({
  tag = "button",
  to,
  onClick,
  onItemClick,
  baseClassName = "block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900",
  className = "",
  children,
}) => {
  const combinedClasses = `${baseClassName} ${className}`.trim();

  const handleClick = (event: React.MouseEvent) => {
    if (tag === "button") {
      event.preventDefault();
    }
    if (onClick) onClick();
    if (onItemClick) onItemClick();
  };

  if (tag === "a" && to) {
    return (
      <Link href={to} className={combinedClasses} onClick={handleClick}>
        {children}
      </Link>
    );
  }

  return (
    <button onClick={handleClick} className={combinedClasses}>
      {children}
    </button>
  );
};
```

- [ ] **Step 3: Create `resources/js/layouts/Backdrop.tsx`**

Copy from template verbatim (no react-router dependency):

```tsx
import { useSidebar } from "@/context/SidebarContext";

const Backdrop: React.FC = () => {
  const { isMobileOpen, toggleMobileSidebar } = useSidebar();

  if (!isMobileOpen) return null;

  return (
    <div
      className="fixed inset-0 z-40 bg-gray-900/50 lg:hidden"
      onClick={toggleMobileSidebar}
    />
  );
};

export default Backdrop;
```

- [ ] **Step 4: Create `resources/js/layouts/SidebarWidget.tsx`**

Replace the TailAdmin promo with a simple placeholder:

```tsx
export default function SidebarWidget() {
  return (
    <div className="mx-auto mb-10 w-full max-w-60 rounded-2xl bg-gray-50 px-4 py-5 text-center dark:bg-white/[0.03]">
      <h3 className="mb-2 font-semibold text-gray-900 dark:text-white">
        Sintux
      </h3>
      <p className="mb-4 text-gray-500 text-theme-sm dark:text-gray-400">
        Accounting & ERP System
      </p>
    </div>
  );
}
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/ui/dropdown/ resources/js/layouts/Backdrop.tsx resources/js/layouts/SidebarWidget.tsx
git commit -m "feat: add dropdown, backdrop, sidebar widget components"
```

---

### Task 8: Create AppSidebar

**Files:**
- Create: `resources/js/layouts/AppSidebar.tsx`

**Interfaces:**
- Consumes: `useSidebar` from `SidebarContext` (Task 5), icons from `resources/js/icons/` (Task 3), `SidebarWidget` (Task 7)
- Consumes: Inertia `Link` from `@inertiajs/react`, `usePage().url` for active state
- Produces: Sidebar component used by `AppLayout` (Task 10)

- [ ] **Step 1: Create `resources/js/layouts/AppSidebar.tsx`**

Adapt from template. Key changes:
- `import { Link, usePage } from "@inertiajs/react"` instead of `react-router`
- `usePage().url` instead of `useLocation().pathname`
- `href` instead of `to` on `<Link>`
- Import icons from `@/icons`
- Keep the same nav structure but use Inertia routes

```tsx
import { useCallback, useEffect, useRef, useState } from "react";
import { Link, usePage } from "@inertiajs/react";

import {
  BoxCubeIcon,
  CalenderIcon,
  ChevronDownIcon,
  GridIcon,
  HorizontaLDots,
  ListIcon,
  PageIcon,
  PieChartIcon,
  PlugInIcon,
  TableIcon,
  UserCircleIcon,
} from "@/icons";
import { useSidebar } from "@/context/SidebarContext";
import SidebarWidget from "./SidebarWidget";

type NavItem = {
  name: string;
  icon: React.ReactNode;
  path?: string;
  subItems?: { name: string; path: string; pro?: boolean; new?: boolean }[];
};

const navItems: NavItem[] = [
  {
    icon: <GridIcon />,
    name: "Dashboard",
    subItems: [{ name: "Ecommerce", path: "/", pro: false }],
  },
  {
    icon: <CalenderIcon />,
    name: "Calendar",
    path: "/calendar",
  },
  {
    icon: <UserCircleIcon />,
    name: "User Profile",
    path: "/profile",
  },
  {
    name: "Forms",
    icon: <ListIcon />,
    subItems: [{ name: "Form Elements", path: "/form-elements", pro: false }],
  },
  {
    name: "Tables",
    icon: <TableIcon />,
    subItems: [{ name: "Basic Tables", path: "/basic-tables", pro: false }],
  },
  {
    name: "Pages",
    icon: <PageIcon />,
    subItems: [
      { name: "Blank Page", path: "/blank", pro: false },
      { name: "404 Error", path: "/error-404", pro: false },
    ],
  },
];

const othersItems: NavItem[] = [
  {
    icon: <PieChartIcon />,
    name: "Charts",
    subItems: [
      { name: "Line Chart", path: "/line-chart", pro: false },
      { name: "Bar Chart", path: "/bar-chart", pro: false },
    ],
  },
  {
    icon: <BoxCubeIcon />,
    name: "UI Elements",
    subItems: [
      { name: "Alerts", path: "/alerts", pro: false },
      { name: "Avatar", path: "/avatars", pro: false },
      { name: "Badge", path: "/badge", pro: false },
      { name: "Buttons", path: "/buttons", pro: false },
      { name: "Images", path: "/images", pro: false },
      { name: "Videos", path: "/videos", pro: false },
    ],
  },
  {
    icon: <PlugInIcon />,
    name: "Authentication",
    subItems: [
      { name: "Sign In", path: "/signin", pro: false },
      { name: "Sign Up", path: "/signup", pro: false },
    ],
  },
];

const AppSidebar: React.FC = () => {
  const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
  const { url } = usePage();

  const [openSubmenu, setOpenSubmenu] = useState<{
    type: "main" | "others";
    index: number;
  } | null>(null);
  const [subMenuHeight, setSubMenuHeight] = useState<Record<string, number>>(
    {}
  );
  const subMenuRefs = useRef<Record<string, HTMLDivElement | null>>({});

  const isActive = useCallback(
    (path: string) => url === path,
    [url]
  );

  useEffect(() => {
    let submenuMatched = false;
    ["main", "others"].forEach((menuType) => {
      const items = menuType === "main" ? navItems : othersItems;
      items.forEach((nav, index) => {
        if (nav.subItems) {
          nav.subItems.forEach((subItem) => {
            if (isActive(subItem.path)) {
              setOpenSubmenu({
                type: menuType as "main" | "others",
                index,
              });
              submenuMatched = true;
            }
          });
        }
      });
    });

    if (!submenuMatched) {
      setOpenSubmenu(null);
    }
  }, [url, isActive]);

  useEffect(() => {
    if (openSubmenu !== null) {
      const key = `${openSubmenu.type}-${openSubmenu.index}`;
      if (subMenuRefs.current[key]) {
        setSubMenuHeight((prevHeights) => ({
          ...prevHeights,
          [key]: subMenuRefs.current[key]?.scrollHeight || 0,
        }));
      }
    }
  }, [openSubmenu]);

  const handleSubmenuToggle = (index: number, menuType: "main" | "others") => {
    setOpenSubmenu((prevOpenSubmenu) => {
      if (
        prevOpenSubmenu &&
        prevOpenSubmenu.type === menuType &&
        prevOpenSubmenu.index === index
      ) {
        return null;
      }
      return { type: menuType, index };
    });
  };

  const renderMenuItems = (items: NavItem[], menuType: "main" | "others") => (
    <ul className="flex flex-col gap-4">
      {items.map((nav, index) => (
        <li key={nav.name}>
          {nav.subItems ? (
            <button
              onClick={() => handleSubmenuToggle(index, menuType)}
              className={`menu-item group ${
                openSubmenu?.type === menuType && openSubmenu?.index === index
                  ? "menu-item-active"
                  : "menu-item-inactive"
              } cursor-pointer ${
                !isExpanded && !isHovered
                  ? "lg:justify-center"
                  : "lg:justify-start"
              }`}
            >
              <span
                className={`menu-item-icon-size  ${
                  openSubmenu?.type === menuType && openSubmenu?.index === index
                    ? "menu-item-icon-active"
                    : "menu-item-icon-inactive"
                }`}
              >
                {nav.icon}
              </span>
              {(isExpanded || isHovered || isMobileOpen) && (
                <span className="menu-item-text">{nav.name}</span>
              )}
              {(isExpanded || isHovered || isMobileOpen) && (
                <ChevronDownIcon
                  className={`ml-auto w-5 h-5 transition-transform duration-200 ${
                    openSubmenu?.type === menuType &&
                    openSubmenu?.index === index
                      ? "rotate-180 text-brand-500"
                      : ""
                  }`}
                />
              )}
            </button>
          ) : (
            nav.path && (
              <Link
                href={nav.path}
                className={`menu-item group ${
                  isActive(nav.path) ? "menu-item-active" : "menu-item-inactive"
                }`}
              >
                <span
                  className={`menu-item-icon-size ${
                    isActive(nav.path)
                      ? "menu-item-icon-active"
                      : "menu-item-icon-inactive"
                  }`}
                >
                  {nav.icon}
                </span>
                {(isExpanded || isHovered || isMobileOpen) && (
                  <span className="menu-item-text">{nav.name}</span>
                )}
              </Link>
            )
          )}
          {nav.subItems && (isExpanded || isHovered || isMobileOpen) && (
            <div
              ref={(el) => {
                subMenuRefs.current[`${menuType}-${index}`] = el;
              }}
              className="overflow-hidden transition-all duration-300"
              style={{
                height:
                  openSubmenu?.type === menuType && openSubmenu?.index === index
                    ? `${subMenuHeight[`${menuType}-${index}`]}px`
                    : "0px",
              }}
            >
              <ul className="mt-2 space-y-1 ml-9">
                {nav.subItems.map((subItem) => (
                  <li key={subItem.name}>
                    <Link
                      href={subItem.path}
                      className={`menu-dropdown-item ${
                        isActive(subItem.path)
                          ? "menu-dropdown-item-active"
                          : "menu-dropdown-item-inactive"
                      }`}
                    >
                      {subItem.name}
                      <span className="flex items-center gap-1 ml-auto">
                        {subItem.new && (
                          <span
                            className={`ml-auto ${
                              isActive(subItem.path)
                                ? "menu-dropdown-badge-active"
                                : "menu-dropdown-badge-inactive"
                            } menu-dropdown-badge`}
                          >
                            new
                          </span>
                        )}
                        {subItem.pro && (
                          <span
                            className={`ml-auto ${
                              isActive(subItem.path)
                                ? "menu-dropdown-badge-active"
                                : "menu-dropdown-badge-inactive"
                            } menu-dropdown-badge`}
                          >
                            pro
                          </span>
                        )}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </li>
      ))}
    </ul>
  );

  return (
    <aside
      className={`fixed mt-16 flex flex-col lg:mt-0 top-0 px-5 left-0 bg-white dark:bg-gray-900 dark:border-gray-800 text-gray-900 h-screen transition-all duration-300 ease-in-out z-50 border-r border-gray-200 
        ${
          isExpanded || isMobileOpen
            ? "w-[290px]"
            : isHovered
            ? "w-[290px]"
            : "w-[90px]"
        }
        ${isMobileOpen ? "translate-x-0" : "-translate-x-full"}
        lg:translate-x-0`}
      onMouseEnter={() => !isExpanded && setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      <div
        className={`py-8 flex ${
          !isExpanded && !isHovered ? "lg:justify-center" : "justify-start"
        }`}
      >
        <Link href="/">
          {isExpanded || isHovered || isMobileOpen ? (
            <>
              <img
                className="dark:hidden"
                src="/images/logo/logo.svg"
                alt="Logo"
                width={150}
                height={40}
              />
              <img
                className="hidden dark:block"
                src="/images/logo/logo-dark.svg"
                alt="Logo"
                width={150}
                height={40}
              />
            </>
          ) : (
            <img
              src="/images/logo/logo-icon.svg"
              alt="Logo"
              width={32}
              height={32}
            />
          )}
        </Link>
      </div>
      <div className="flex flex-col overflow-y-auto duration-300 ease-linear no-scrollbar">
        <nav className="mb-6">
          <div className="flex flex-col gap-4">
            <div>
              <h2
                className={`mb-4 text-xs uppercase flex leading-[20px] text-gray-400 ${
                  !isExpanded && !isHovered
                    ? "lg:justify-center"
                    : "justify-start"
                }`}
              >
                {isExpanded || isHovered || isMobileOpen ? (
                  "Menu"
                ) : (
                  <HorizontaLDots className="size-6" />
                )}
              </h2>
              {renderMenuItems(navItems, "main")}
            </div>
            <div className="">
              <h2
                className={`mb-4 text-xs uppercase flex leading-[20px] text-gray-400 ${
                  !isExpanded && !isHovered
                    ? "lg:justify-center"
                    : "justify-start"
                }`}
              >
                {isExpanded || isHovered || isMobileOpen ? (
                  "Others"
                ) : (
                  <HorizontaLDots />
                )}
              </h2>
              {renderMenuItems(othersItems, "others")}
            </div>
          </div>
        </nav>
        {isExpanded || isHovered || isMobileOpen ? <SidebarWidget /> : null}
      </div>
    </aside>
  );
};

export default AppSidebar;
```

- [ ] **Step 2: Commit**

```bash
git add resources/js/layouts/AppSidebar.tsx
git commit -m "feat: add TailAdmin AppSidebar adapted for Inertia.js"
```

---

### Task 9: Create Header Components

**Files:**
- Create: `resources/js/components/common/ThemeToggleButton.tsx`
- Create: `resources/js/components/header/NotificationDropdown.tsx`
- Create: `resources/js/components/header/UserDropdown.tsx`
- Create: `resources/js/layouts/AppHeader.tsx`

**Interfaces:**
- Consumes: `useSidebar` (Task 5), `Dropdown`/`DropdownItem` (Task 7), `ThemeToggleButton`/`NotificationDropdown`/`UserDropdown` (this task)
- Produces: `AppHeader` used by `AppLayout` (Task 10)

- [ ] **Step 1: Create `resources/js/components/common/ThemeToggleButton.tsx`**

Adapt from template. Replace `useTheme` from template's `ThemeContext` with existing `useAppearance` hook:

```tsx
import { useAppearance } from "@/hooks/use-appearance";

export const ThemeToggleButton: React.FC = () => {
  const { resolvedAppearance, updateAppearance } = useAppearance();

  const toggleTheme = () => {
    updateAppearance(resolvedAppearance === "light" ? "dark" : "light");
  };

  return (
    <button
      onClick={toggleTheme}
      className="relative flex items-center justify-center text-gray-500 transition-colors bg-white border border-gray-200 rounded-full hover:text-dark-900 h-11 w-11 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
    >
      <svg
        className="hidden dark:block"
        width="20"
        height="20"
        viewBox="0 0 20 20"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          fillRule="evenodd"
          clipRule="evenodd"
          d="M9.99998 1.5415C10.4142 1.5415 10.75 1.87729 10.75 2.2915V3.5415C10.75 3.95572 10.4142 4.2915 9.99998 4.2915C9.58577 4.2915 9.24998 3.95572 9.24998 3.5415V2.2915C9.24998 1.87729 9.58577 1.5415 9.99998 1.5415ZM10.0009 6.79327C8.22978 6.79327 6.79402 8.22904 6.79402 10.0001C6.79402 11.7712 8.22978 13.207 10.0009 13.207C11.772 13.207 13.2078 11.7712 13.2078 10.0001C13.2078 8.22904 11.772 6.79327 10.0009 6.79327ZM5.29402 10.0001C5.29402 7.40061 7.40135 5.29327 10.0009 5.29327C12.6004 5.29327 14.7078 7.40061 14.7078 10.0001C14.7078 12.5997 12.6004 14.707 10.0009 14.707C7.40135 14.707 5.29402 12.5997 5.29402 10.0001ZM15.9813 5.08035C16.2742 4.78746 16.2742 4.31258 15.9813 4.01969C15.6884 3.7268 15.2135 3.7268 14.9207 4.01969L14.0368 4.90357C13.7439 5.19647 13.7439 5.67134 14.0368 5.96423C14.3297 6.25713 14.8045 6.25713 15.0974 5.96423L15.9813 5.08035ZM18.4577 10.0001C18.4577 10.4143 18.1219 10.7501 17.7077 10.7501H16.4577C16.0435 10.7501 15.7077 10.4143 15.7077 10.0001C15.7077 9.58592 16.0435 9.25013 16.4577 9.25013H17.7077C18.1219 9.25013 18.4577 9.58592 18.4577 10.0001ZM14.9207 15.9806C15.2135 16.2735 15.6884 16.2735 15.9813 15.9806C16.2742 15.6877 16.2742 15.2128 15.9813 14.9199L15.0974 14.036C14.8045 13.7431 14.3297 13.7431 14.0368 14.036C13.7439 14.3289 13.7439 14.8038 14.0368 15.0967L14.9207 15.9806ZM9.99998 15.7088C10.4142 15.7088 10.75 16.0445 10.75 16.4588V17.7088C10.75 18.123 10.4142 18.4588 9.99998 18.4588C9.58577 18.4588 9.24998 18.123 9.24998 17.7088V16.4588C9.24998 16.0445 9.58577 15.7088 9.99998 15.7088ZM5.96356 15.0972C6.25646 14.8043 6.25646 14.3295 5.96356 14.0366C5.67067 13.7437 5.1958 13.7437 4.9029 14.0366L4.01902 14.9204C3.72613 15.2133 3.72613 15.6882 4.01902 15.9811C4.31191 16.274 4.78679 16.274 5.07968 15.9811L5.96356 15.0972ZM4.29224 10.0001C4.29224 10.4143 3.95645 10.7501 3.54224 10.7501H2.29224C1.87802 10.7501 1.54224 10.4143 1.54224 10.0001C1.54224 9.58592 1.87802 9.25013 2.29224 9.25013H3.54224C3.95645 9.25013 4.29224 9.58592 4.29224 10.0001ZM5.07968 4.01923C4.78679 3.72634 4.31191 3.72634 4.01902 4.01923C3.72613 4.31213 3.72613 4.787 4.01902 5.07989L4.9029 5.96377C5.1958 6.25666 5.67067 6.25666 5.96356 5.96377C6.25646 5.67088 6.25646 5.19601 5.96356 4.90312L5.07968 4.01923Z"
          fill="currentColor"
        />
      </svg>
      <svg
        className="dark:hidden"
        width="20"
        height="20"
        viewBox="0 0 20 20"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
      >
        <path
          d="M17.4547 11.97L18.1799 12.1611C18.265 11.8383 18.1265 11.4982 17.8401 11.3266C17.5538 11.1551 17.1885 11.1934 16.944 11.4207L17.4547 11.97ZM8.0306 2.5459L8.57989 3.05657C8.80718 2.81209 8.84554 2.44682 8.67398 2.16046C8.50243 1.8741 8.16227 1.73559 7.83948 1.82066L8.0306 2.5459ZM12.9154 13.0035C9.64678 13.0035 6.99707 10.3538 6.99707 7.08524H5.49707C5.49707 11.1823 8.81835 14.5035 12.9154 14.5035V13.0035ZM16.944 11.4207C15.8869 12.4035 14.4721 13.0035 12.9154 13.0035V14.5035C14.8657 14.5035 16.6418 13.7499 17.9654 12.5193L16.944 11.4207ZM16.7295 11.7789C15.9437 14.7607 13.2277 16.9586 10.0003 16.9586V18.4586C13.9257 18.4586 17.2249 15.7853 18.1799 12.1611L16.7295 11.7789ZM10.0003 16.9586C6.15734 16.9586 3.04199 13.8433 3.04199 10.0003H1.54199C1.54199 14.6717 5.32892 18.4586 10.0003 18.4586V16.9586ZM3.04199 10.0003C3.04199 6.77289 5.23988 4.05695 8.22173 3.27114L7.83948 1.82066C4.21532 2.77574 1.54199 6.07486 1.54199 10.0003H3.04199ZM6.99707 7.08524C6.99707 5.52854 7.5971 4.11366 8.57989 3.05657L7.48132 2.03522C6.25073 3.35885 5.49707 5.13487 5.49707 7.08524H6.99707Z"
          fill="currentColor"
        />
      </svg>
    </button>
  );
};
```

- [ ] **Step 2: Create `resources/js/components/header/NotificationDropdown.tsx`**

Adapt from template — replace `react-router` `Link` with Inertia `Link`, use `href` instead of `to`:

```tsx
import { useState } from "react";
import { Dropdown } from "@/components/ui/dropdown/Dropdown";
import { DropdownItem } from "@/components/ui/dropdown/DropdownItem";
import { Link } from "@inertiajs/react";

export default function NotificationDropdown() {
  const [isOpen, setIsOpen] = useState(false);
  const [notifying, setNotifying] = useState(true);

  function toggleDropdown() {
    setIsOpen(!isOpen);
  }

  function closeDropdown() {
    setIsOpen(false);
  }

  const handleClick = () => {
    toggleDropdown();
    setNotifying(false);
  };
  return (
    <div className="relative">
      <button
        className="relative flex items-center justify-center text-gray-500 transition-colors bg-white border border-gray-200 rounded-full dropdown-toggle hover:text-gray-700 h-11 w-11 hover:bg-gray-100 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
        onClick={handleClick}
      >
        <span
          className={`absolute right-0 top-0.5 z-10 h-2 w-2 rounded-full bg-orange-400 ${
            !notifying ? "hidden" : "flex"
          }`}
        >
          <span className="absolute inline-flex w-full h-full bg-orange-400 rounded-full opacity-75 animate-ping"></span>
        </span>
        <svg
          className="fill-current"
          width="20"
          height="20"
          viewBox="0 0 20 20"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            fillRule="evenodd"
            clipRule="evenodd"
            d="M10.75 2.29248C10.75 1.87827 10.4143 1.54248 10 1.54248C9.58583 1.54248 9.25004 1.87827 9.25004 2.29248V2.83613C6.08266 3.20733 3.62504 5.9004 3.62504 9.16748V14.4591H3.33337C2.91916 14.4591 2.58337 14.7949 2.58337 15.2091C2.58337 15.6234 2.91916 15.9591 3.33337 15.9591H4.37504H15.625H16.6667C17.0809 15.9591 17.4167 15.6234 17.4167 15.2091C17.4167 14.7949 17.0809 14.4591 16.6667 14.4591H16.375V9.16748C16.375 5.9004 13.9174 3.20733 10.75 2.83613V2.29248ZM14.875 14.4591V9.16748C14.875 6.47509 12.6924 4.29248 10 4.29248C7.30765 4.29248 5.12504 6.47509 5.12504 9.16748V14.4591H14.875ZM8.00004 17.7085C8.00004 18.1228 8.33583 18.4585 8.75004 18.4585H11.25C11.6643 18.4585 12 18.1228 12 17.7085C12 17.2943 11.6643 16.9585 11.25 16.9585H8.75004C8.33583 16.9585 8.00004 17.2943 8.00004 17.7085Z"
            fill="currentColor"
          />
        </svg>
      </button>
      <Dropdown
        isOpen={isOpen}
        onClose={closeDropdown}
        className="absolute -right-[240px] mt-[17px] flex h-[480px] w-[350px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark sm:w-[361px] lg:right-0"
      >
        <div className="flex items-center justify-between pb-3 mb-3 border-b border-gray-100 dark:border-gray-700">
          <h5 className="text-lg font-semibold text-gray-800 dark:text-gray-200">
            Notification
          </h5>
          <button
            onClick={toggleDropdown}
            className="text-gray-500 transition dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200"
          >
            <svg
              className="fill-current"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              xmlns="http://www.w3.org/2000/svg"
            >
              <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z"
                fill="currentColor"
              />
            </svg>
          </button>
        </div>
        <ul className="flex flex-col h-auto overflow-y-auto custom-scrollbar">
          <li>
            <DropdownItem
              onItemClick={closeDropdown}
              className="flex gap-3 rounded-lg border-b border-gray-100 p-3 px-4.5 py-3 hover:bg-gray-100 dark:border-gray-800 dark:hover:bg-white/5"
            >
              <span className="relative block w-full h-10 rounded-full z-1 max-w-10">
                <span className="flex items-center justify-center w-full h-full rounded-full bg-brand-100 text-brand-600 dark:bg-brand-500/20 dark:text-brand-400">
                  <svg width="16" height="16" viewBox="0 0 20 20" fill="none"><path d="M10 2C5.58 2 2 5.58 2 10s3.58 8 8 8 8-3.58 8-8-3.58-8-8-8zm0 14.5c-3.58 0-6.5-2.92-6.5-6.5S6.42 3.5 10 3.5s6.5 2.92 6.5 6.5-2.92 6.5-6.5 6.5z" fill="currentColor"/></svg>
                </span>
                <span className="absolute bottom-0 right-0 z-10 h-2.5 w-full max-w-2.5 rounded-full border-[1.5px] border-white bg-success-500 dark:border-gray-900"></span>
              </span>
              <span className="block">
                <span className="mb-1.5 block text-theme-sm text-gray-500 dark:text-gray-400 space-x-1">
                  <span className="font-medium text-gray-800 dark:text-white/90">System</span>
                  <span> Welcome to Sintux!</span>
                </span>
                <span className="flex items-center gap-2 text-gray-500 text-theme-xs dark:text-gray-400">
                  <span>Just now</span>
                </span>
              </span>
            </DropdownItem>
          </li>
        </ul>
        <Link
          href="/"
          className="block px-4 py-2 mt-3 text-sm font-medium text-center text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700"
        >
          View All Notifications
        </Link>
      </Dropdown>
    </div>
  );
}
```

- [ ] **Step 3: Create `resources/js/components/header/UserDropdown.tsx`**

Adapt from template. Replace hardcoded user data with Inertia shared auth user. Replace `react-router` `Link` with Inertia `Link`:

```tsx
import { useState } from "react";
import { DropdownItem } from "@/components/ui/dropdown/DropdownItem";
import { Dropdown } from "@/components/ui/dropdown/Dropdown";
import { Link, usePage } from "@inertiajs/react";

export default function UserDropdown() {
  const [isOpen, setIsOpen] = useState(false);
  const { auth } = usePage().props as unknown as { auth: { user: { name: string; email: string } } };

  function toggleDropdown() {
    setIsOpen(!isOpen);
  }

  function closeDropdown() {
    setIsOpen(false);
  }
  return (
    <div className="relative">
      <button
        onClick={toggleDropdown}
        className="flex items-center text-gray-700 dropdown-toggle dark:text-gray-400"
      >
        <span className="mr-3 overflow-hidden rounded-full h-11 w-11 bg-gray-200 flex items-center justify-center">
          <span className="text-sm font-medium text-gray-600">
            {auth.user.name.charAt(0).toUpperCase()}
          </span>
        </span>

        <span className="block mr-1 font-medium text-theme-sm">{auth.user.name}</span>
        <svg
          className={`stroke-gray-500 dark:stroke-gray-400 transition-transform duration-200 ${
            isOpen ? "rotate-180" : ""
          }`}
          width="18"
          height="20"
          viewBox="0 0 18 20"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path
            d="M4.3125 8.65625L9 13.3437L13.6875 8.65625"
            stroke="currentColor"
            strokeWidth="1.5"
            strokeLinecap="round"
            strokeLinejoin="round"
          />
        </svg>
      </button>

      <Dropdown
        isOpen={isOpen}
        onClose={closeDropdown}
        className="absolute right-0 mt-[17px] flex w-[260px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
      >
        <div>
          <span className="block font-medium text-gray-700 text-theme-sm dark:text-gray-400">
            {auth.user.name}
          </span>
          <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">
            {auth.user.email}
          </span>
        </div>

        <ul className="flex flex-col gap-1 pt-4 pb-3 border-b border-gray-200 dark:border-gray-800">
          <li>
            <DropdownItem
              onItemClick={closeDropdown}
              tag="a"
              to="/settings/profile"
              className="flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
            >
              <svg className="fill-gray-500 group-hover:fill-gray-700 dark:fill-gray-400 dark:group-hover:fill-gray-300" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fillRule="evenodd" clipRule="evenodd" d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z" fill=""/></svg>
              Edit profile
            </DropdownItem>
          </li>
          <li>
            <DropdownItem
              onItemClick={closeDropdown}
              tag="a"
              to="/settings/profile"
              className="flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
            >
              <svg className="fill-gray-500 group-hover:fill-gray-700 dark:fill-gray-400 dark:group-hover:fill-gray-300" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fillRule="evenodd" clipRule="evenodd" d="M10.4858 3.5L13.5182 3.5C13.9233 3.5 14.2518 3.82851 14.2518 4.23377C14.2518 5.9529 16.1129 7.02795 17.602 6.1682C17.9528 5.96567 18.4014 6.08586 18.6039 6.43667L20.1203 9.0631C20.3229 9.41407 20.2027 9.86286 19.8517 10.0655C18.3625 10.9253 18.3625 13.0747 19.8517 13.9345C20.2026 14.1372 20.3229 14.5859 20.1203 14.9369L18.6039 17.5634C18.4013 17.9142 17.9528 18.0344 17.602 17.8318C16.1129 16.9721 14.2518 18.0471 14.2518 19.7663C14.2518 20.1715 13.9233 20.5 13.5182 20.5H10.4858C10.0804 20.5 9.75182 20.1714 9.75182 19.766C9.75182 18.0461 7.88983 16.9717 6.40067 17.8314C6.04945 18.0342 5.60037 17.9139 5.39767 17.5628L3.88167 14.937C3.67903 14.586 3.79928 14.1372 4.15026 13.9346C5.63949 13.0748 5.63946 10.9253 4.15025 10.0655C3.79926 9.86282 3.67901 9.41401 3.88165 9.06303L5.39764 6.43725C5.60034 6.08617 6.04943 5.96581 6.40065 6.16858C7.88982 7.02836 9.75182 5.9539 9.75182 4.23399C9.75182 3.82862 10.0804 3.5 10.4858 3.5ZM13.5182 2L10.4858 2C9.25201 2 8.25182 3.00019 8.25182 4.23399C8.25182 4.79884 7.64013 5.15215 7.15065 4.86955C6.08213 4.25263 4.71559 4.61859 4.0986 5.68725L2.58261 8.31303C1.96575 9.38146 2.33183 10.7477 3.40025 11.3645C3.88948 11.647 3.88947 12.3531 3.40026 12.6355C2.33184 13.2524 1.96578 14.6186 2.58263 15.687L4.09863 18.3128C4.71562 19.3814 6.08215 19.7474 7.15067 19.1305C7.64015 18.8479 8.25182 19.2012 8.25182 19.766C8.25182 20.9998 9.25201 22 10.4858 22H13.5182C14.7519 22 15.7518 20.9998 15.7518 19.7663C15.7518 19.2015 16.3632 18.8487 16.852 19.1309C17.9202 19.7476 19.2862 19.3816 19.9029 18.3134L21.4193 15.6869C22.0361 14.6185 21.6701 13.2523 20.6017 12.6355C20.1125 12.3531 20.1125 11.647 20.6017 11.3645C21.6701 10.7477 22.0362 9.38152 21.4193 8.3131L19.903 5.68667C19.2862 4.61842 17.9202 4.25241 16.852 4.86917C16.3632 5.15138 15.7518 4.79856 15.7518 4.23377C15.7518 3.00024 14.7519 2 13.5182 2ZM9.6659 11.9999C9.6659 10.7103 10.7113 9.66493 12.0009 9.66493C13.2905 9.66493 14.3359 10.7103 14.3359 11.9999C14.3359 13.2895 13.2905 14.3349 12.0009 14.3349C10.7113 14.3349 9.6659 13.2895 9.6659 11.9999ZM12.0009 8.16493C9.88267 8.16493 8.1659 9.8817 8.1659 11.9999C8.1659 14.1181 9.88267 15.8349 12.0009 15.8349C14.1191 15.8349 15.8359 14.1181 15.8359 11.9999C15.8359 9.8817 14.1191 8.16493 12.0009 8.16493Z" fill=""/></svg>
              Account settings
            </DropdownItem>
          </li>
        </ul>
        <Link
          href="/logout"
          method="post"
          as="button"
          className="flex items-center gap-3 px-3 py-2 mt-3 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300"
        >
          <svg className="fill-gray-500 group-hover:fill-gray-700 dark:group-hover:fill-gray-300" width="24" height="24" viewBox="0 0 24 24" fill="none"><path fillRule="evenodd" clipRule="evenodd" d="M15.1007 19.247C14.6865 19.247 14.3507 18.9112 14.3507 18.497L14.3507 14.245H12.8507V18.497C12.8507 19.7396 13.8581 20.747 15.1007 20.747H18.5007C19.7434 20.747 20.7507 19.7396 20.7507 18.497L20.7507 5.49609C20.7507 4.25345 19.7433 3.24609 18.5007 3.24609H15.1007C13.8581 3.24609 12.8507 4.25345 12.8507 5.49609V9.74501L14.3507 9.74501V5.49609C14.3507 5.08188 14.6865 4.74609 15.1007 4.74609L18.5007 4.74609C18.9149 4.74609 19.2507 5.08188 19.2507 5.49609L19.2507 18.497C19.2507 18.9112 18.9149 19.247 18.5007 19.247H15.1007ZM3.25073 11.9984C3.25073 12.2144 3.34204 12.4091 3.48817 12.546L8.09483 17.1556C8.38763 17.4485 8.86251 17.4487 9.15549 17.1559C9.44848 16.8631 9.44863 16.3882 9.15583 16.0952L5.81116 12.7484L16.0007 12.7484C16.4149 12.7484 16.7507 12.4127 16.7507 11.9984C16.7507 11.5842 16.4149 11.2484 16.0007 11.2484L5.81528 11.2484L9.15585 7.90554C9.44864 7.61255 9.44847 7.13767 9.15547 6.84488C8.86248 6.55209 8.3876 6.55226 8.09481 6.84525L3.52309 11.4202C3.35673 11.5577 3.25073 11.7657 3.25073 11.9984Z" fill=""/></svg>
          Sign out
        </Link>
      </Dropdown>
    </div>
  );
}
```

- [ ] **Step 4: Create `resources/js/layouts/AppHeader.tsx`**

Adapt from template. Replace `react-router` `Link` with Inertia `Link`:

```tsx
import { useEffect, useRef, useState } from "react";

import { Link } from "@inertiajs/react";
import { useSidebar } from "@/context/SidebarContext";
import { ThemeToggleButton } from "@/components/common/ThemeToggleButton";
import NotificationDropdown from "@/components/header/NotificationDropdown";
import UserDropdown from "@/components/header/UserDropdown";

const AppHeader: React.FC = () => {
  const [isApplicationMenuOpen, setApplicationMenuOpen] = useState(false);

  const { isMobileOpen, toggleSidebar, toggleMobileSidebar } = useSidebar();

  const handleToggle = () => {
    if (window.innerWidth >= 1024) {
      toggleSidebar();
    } else {
      toggleMobileSidebar();
    }
  };

  const toggleApplicationMenu = () => {
    setApplicationMenuOpen(!isApplicationMenuOpen);
  };

  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if ((event.metaKey || event.ctrlKey) && event.key === "k") {
        event.preventDefault();
        inputRef.current?.focus();
      }
    };

    document.addEventListener("keydown", handleKeyDown);

    return () => {
      document.removeEventListener("keydown", handleKeyDown);
    };
  }, []);

  return (
    <header className="sticky top-0 flex w-full bg-white border-gray-200 z-99999 dark:border-gray-800 dark:bg-gray-900 lg:border-b">
      <div className="flex flex-col items-center justify-between grow lg:flex-row lg:px-6">
        <div className="flex items-center justify-between w-full gap-2 px-3 py-3 border-b border-gray-200 dark:border-gray-800 sm:gap-4 lg:justify-normal lg:border-b-0 lg:px-0 lg:py-4">
          <button
            className="items-center justify-center w-10 h-10 text-gray-500 border-gray-200 rounded-lg z-99999 dark:border-gray-800 lg:flex dark:text-gray-400 lg:h-11 lg:w-11 lg:border"
            onClick={handleToggle}
            aria-label="Toggle Sidebar"
          >
            {isMobileOpen ? (
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fillRule="evenodd" clipRule="evenodd" d="M6.21967 7.28131C5.92678 6.98841 5.92678 6.51354 6.21967 6.22065C6.51256 5.92775 6.98744 5.92775 7.28033 6.22065L11.999 10.9393L16.7176 6.22078C17.0105 5.92789 17.4854 5.92788 17.7782 6.22078C18.0711 6.51367 18.0711 6.98855 17.7782 7.28144L13.0597 12L17.7782 16.7186C18.0711 17.0115 18.0711 17.4863 17.7782 17.7792C17.4854 18.0721 17.0105 18.0721 16.7176 17.7792L11.999 13.0607L7.28033 17.7794C6.98744 18.0722 6.51256 18.0722 6.21967 17.7794C5.92678 17.4865 5.92678 17.0116 6.21967 16.7187L10.9384 12L6.21967 7.28131Z" fill="currentColor" />
              </svg>
            ) : (
              <svg width="16" height="12" viewBox="0 0 16 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path fillRule="evenodd" clipRule="evenodd" d="M0.583252 1C0.583252 0.585788 0.919038 0.25 1.33325 0.25H14.6666C15.0808 0.25 15.4166 0.585786 15.4166 1C15.4166 1.41421 15.0808 1.75 14.6666 1.75L1.33325 1.75C0.919038 1.75 0.583252 1.41422 0.583252 1ZM0.583252 11C0.583252 10.5858 0.919038 10.25 1.33325 10.25L14.6666 10.25C15.0808 10.25 15.4166 10.5858 15.4166 11C15.4166 11.4142 15.0808 11.75 14.6666 11.75L1.33325 11.75C0.919038 11.75 0.583252 11.4142 0.583252 11ZM1.33325 5.25C0.919038 5.25 0.583252 5.58579 0.583252 6C0.583252 6.41421 0.919038 6.75 1.33325 6.75L7.99992 6.75C8.41413 6.75 8.74992 6.41421 8.74992 6C8.74992 5.58579 8.41413 5.25 7.99992 5.25L1.33325 5.25Z" fill="currentColor" />
              </svg>
            )}
          </button>

          <Link href="/" className="lg:hidden">
            <img className="dark:hidden" src="/images/logo/logo.svg" alt="Logo" />
            <img className="hidden dark:block" src="/images/logo/logo-dark.svg" alt="Logo" />
          </Link>

          <button
            onClick={toggleApplicationMenu}
            className="flex items-center justify-center w-10 h-10 text-gray-700 rounded-lg z-99999 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800 lg:hidden"
          >
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path fillRule="evenodd" clipRule="evenodd" d="M5.99902 10.4951C6.82745 10.4951 7.49902 11.1667 7.49902 11.9951V12.0051C7.49902 12.8335 6.82745 13.5051 5.99902 13.5051C5.1706 13.5051 4.49902 12.8335 4.49902 12.0051V11.9951C4.49902 11.1667 5.1706 10.4951 5.99902 10.4951ZM17.999 10.4951C18.8275 10.4951 19.499 11.1667 19.499 11.9951V12.0051C19.499 12.8335 18.8275 13.5051 17.999 13.5051C17.1706 13.5051 16.499 12.8335 16.499 12.0051V11.9951C16.499 11.1667 17.1706 10.4951 17.999 10.4951ZM13.499 11.9951C13.499 11.1667 12.8275 10.4951 11.999 10.4951C11.1706 10.4951 10.499 11.1667 10.499 11.9951V12.0051C10.499 12.8335 11.1706 13.5051 11.999 13.5051C12.8275 13.5051 13.499 12.8335 13.499 12.0051V11.9951Z" fill="currentColor" />
            </svg>
          </button>

          <div className="hidden lg:block">
            <form>
              <div className="relative">
                <span className="absolute -translate-y-1/2 pointer-events-none left-4 top-1/2">
                  <svg className="fill-gray-500 dark:fill-gray-400" width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path fillRule="evenodd" clipRule="evenodd" d="M3.04175 9.37363C3.04175 5.87693 5.87711 3.04199 9.37508 3.04199C12.8731 3.04199 15.7084 5.87693 15.7084 9.37363C15.7084 12.8703 12.8731 15.7053 9.37508 15.7053C5.87711 15.7053 3.04175 12.8703 3.04175 9.37363ZM9.37508 1.54199C5.04902 1.54199 1.54175 5.04817 1.54175 9.37363C1.54175 13.6991 5.04902 17.2053 9.37508 17.2053C11.2674 17.2053 13.003 16.5344 14.357 15.4176L17.177 18.238C17.4699 18.5309 17.9448 18.5309 18.2377 18.238C18.5306 17.9451 18.5306 17.4703 18.2377 17.1774L15.418 14.3573C16.5365 13.0033 17.2084 11.2669 17.2084 9.37363C17.2084 5.04817 13.7011 1.54199 9.37508 1.54199Z" fill="" />
                  </svg>
                </span>
                <input
                  ref={inputRef}
                  type="text"
                  placeholder="Search or type command..."
                  className="dark:bg-dark-900 h-11 w-full rounded-lg border border-gray-200 bg-transparent py-2.5 pl-12 pr-14 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:outline-hidden focus:ring-3 focus:ring-brand-500/10 dark:border-gray-800 dark:bg-gray-900 dark:bg-white/[0.03] dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800 xl:w-[430px]"
                />
                <button className="absolute right-2.5 top-1/2 inline-flex -translate-y-1/2 items-center gap-0.5 rounded-lg border border-gray-200 bg-gray-50 px-[7px] py-[4.5px] text-xs -tracking-[0.2px] text-gray-500 dark:border-gray-800 dark:bg-white/[0.03] dark:text-gray-400">
                  <span> ⌘ </span>
                  <span> K </span>
                </button>
              </div>
            </form>
          </div>
        </div>
        <div
          className={`${
            isApplicationMenuOpen ? "flex" : "hidden"
          } items-center justify-between w-full gap-4 px-5 py-4 lg:flex shadow-theme-md lg:justify-end lg:px-0 lg:shadow-none`}
        >
          <div className="flex items-center gap-2 2xsm:gap-3">
            <ThemeToggleButton />
            <NotificationDropdown />
          </div>
          <UserDropdown />
        </div>
      </div>
    </header>
  );
};

export default AppHeader;
```

- [ ] **Step 5: Commit**

```bash
mkdir -p resources/js/components/common resources/js/components/header
git add resources/js/components/common/ThemeToggleButton.tsx resources/js/components/header/NotificationDropdown.tsx resources/js/components/header/UserDropdown.tsx resources/js/layouts/AppHeader.tsx
git commit -m "feat: add TailAdmin header components adapted for Inertia.js"
```

---

### Task 10: Create AppLayout and Update app.tsx

**Files:**
- Replace: `resources/js/layouts/app-layout.tsx`
- Replace: `resources/js/app.tsx`

**Interfaces:**
- Consumes: `SidebarProvider` (Task 5), `AppSidebar` (Task 8), `AppHeader` (Task 9), `Backdrop` (Task 7)
- Produces: Root layout used by all Inertia pages

- [ ] **Step 1: Replace `resources/js/layouts/app-layout.tsx`**

```tsx
import { SidebarProvider, useSidebar } from "@/context/SidebarContext";
import AppHeader from "@/layouts/AppHeader";
import Backdrop from "@/layouts/Backdrop";
import AppSidebar from "@/layouts/AppSidebar";

const LayoutContent: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const { isExpanded, isHovered, isMobileOpen } = useSidebar();

  return (
    <div className="min-h-screen xl:flex">
      <div>
        <AppSidebar />
        <Backdrop />
      </div>
      <div
        className={`flex-1 transition-all duration-300 ease-in-out ${
          isExpanded || isHovered ? "lg:ml-[290px]" : "lg:ml-[90px]"
        } ${isMobileOpen ? "ml-0" : ""}`}
      >
        <AppHeader />
        <div className="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6">
          {children}
        </div>
      </div>
    </div>
  );
};

export default function AppLayout({ children }: { children: React.ReactNode }) {
  return (
    <SidebarProvider>
      <LayoutContent>{children}</LayoutContent>
    </SidebarProvider>
  );
}
```

- [ ] **Step 2: Replace `resources/js/app.tsx`**

Remove all shadcn/ui imports. Simplify layout routing:

```tsx
import { createInertiaApp } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import { initializeTheme } from '@/hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        if (name === 'welcome') return null;
        if (name.startsWith('auth/')) return AuthLayout;
        return AppLayout;
    },
    strictMode: true,
    progress: {
        color: '#465fff',
    },
});
```

Note: removed `SettingsLayout` from the layout chain for now — settings pages can be re-integrated later.

- [ ] **Step 3: Commit**

```bash
git add resources/js/layouts/app-layout.tsx resources/js/app.tsx
git commit -m "feat: wire TailAdmin layout into Inertia app"
```

---

### Task 11: Update Pages to Remove shadcn/ui Imports

**Files:**
- Modify: `resources/js/pages/dashboard.tsx`
- Modify: `resources/js/pages/auth/login.tsx`
- Modify: `resources/js/pages/auth/forgot-password.tsx`
- Modify: `resources/js/pages/auth/reset-password.tsx`
- Modify: `resources/js/pages/settings/profile.tsx`
- Modify: `resources/js/pages/settings/security.tsx`
- Modify: `resources/js/layouts/auth/auth-simple-layout.tsx`
- Modify: `resources/js/layouts/auth/auth-card-layout.tsx`
- Modify: `resources/js/layouts/settings/layout.tsx`

**Interfaces:**
- All pages must compile without shadcn/ui imports

- [ ] **Step 1: Update `resources/js/pages/dashboard.tsx`**

Replace with simple Tailwind layout (no shadcn):

```tsx
import { Head } from '@inertiajs/react';

export default function Dashboard() {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="aspect-video overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900" />
                    <div className="aspect-video overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900" />
                    <div className="aspect-video overflow-hidden rounded-xl border border-gray-200 bg-gray-50 dark:border-gray-800 dark:bg-gray-900" />
                </div>
                <div className="relative min-h-[100vh] flex-1 overflow-hidden rounded-xl border border-gray-200 md:min-h-min dark:border-gray-800" />
            </div>
        </>
    );
}
```

- [ ] **Step 2: Update auth pages**

For each auth page (`login.tsx`, `forgot-password.tsx`, `reset-password.tsx`), replace shadcn imports with plain HTML/Tailwind equivalents. Example for `login.tsx`:

```tsx
import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEventHandler } from 'react';
import InputError from '@/components/input-error';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
};

export default function Login({ status, canResetPassword }: { status?: string; canResetPassword: boolean }) {
    const { data, setData, post, processing, errors, reset } = useForm<Required<LoginForm>>({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Log in" />
            {status && <div className="mb-4 text-sm font-medium text-green-600">{status}</div>}
            <form onSubmit={submit}>
                <div className="space-y-4">
                    <div>
                        <label htmlFor="email" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                        <input
                            id="email"
                            type="email"
                            required
                            autoFocus
                            autoComplete="email"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div>
                        <label htmlFor="password" className="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                        <input
                            id="password"
                            type="password"
                            required
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            className="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
                        />
                        <InputError message={errors.password} />
                    </div>
                    <div className="flex items-center gap-2">
                        <input
                            id="remember"
                            type="checkbox"
                            checked={data.remember}
                            onChange={(e) => setData('remember', e.target.checked)}
                            className="rounded border-gray-300"
                        />
                        <label htmlFor="remember" className="text-sm text-gray-600 dark:text-gray-400">Remember me</label>
                    </div>
                    <button
                        type="submit"
                        disabled={processing}
                        className="w-full rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600 disabled:opacity-50"
                    >
                        Log in
                    </button>
                    {canResetPassword && (
                        <Link href={route('password.request')} className="text-sm text-brand-500 hover:text-brand-600">
                            Forgot your password?
                        </Link>
                    )}
                </div>
            </form>
        </>
    );
}
```

Apply similar pattern to `forgot-password.tsx` and `reset-password.tsx`.

- [ ] **Step 3: Update `resources/js/layouts/auth/auth-simple-layout.tsx`**

Replace shadcn CSS variables with Tailwind:

```tsx
import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center gap-6 bg-gray-50 p-6 md:p-10 dark:bg-gray-950">
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8">
                    <div className="flex flex-col items-center gap-4">
                        <Link href={home()} className="flex flex-col items-center gap-2 font-medium">
                            <div className="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                                <AppLogoIcon className="size-9 fill-current text-gray-900 dark:text-white" />
                            </div>
                            <span className="sr-only">{title}</span>
                        </Link>
                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium text-gray-900 dark:text-white">{title}</h1>
                            <p className="text-center text-sm text-gray-500 dark:text-gray-400">{description}</p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 4: Update `resources/js/layouts/settings/layout.tsx`**

Replace shadcn `Button` with plain Tailwind links:

```tsx
import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
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

    return (
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
}
```

- [ ] **Step 5: Update remaining settings pages**

For `resources/js/pages/settings/profile.tsx` and `security.tsx`, replace shadcn `Button`, `Input`, `Label` with plain HTML/Tailwind equivalents. Follow same pattern as login page.

- [ ] **Step 6: Commit**

```bash
git add resources/js/pages/ resources/js/layouts/ resources/js/app.tsx
git commit -m "feat: update all pages to remove shadcn/ui dependencies"
```

---

### Task 12: Delete shadcn/ui Components and Cleanup

**Files:**
- Delete: `resources/js/components/ui/` (entire directory)
- Delete: `components.json`
- Delete: `resources/js/components/app-shell.tsx`
- Delete: `resources/js/components/app-content.tsx`
- Delete: `resources/js/components/app-sidebar.tsx`
- Delete: `resources/js/components/app-sidebar-header.tsx`
- Delete: `resources/js/components/app-header.tsx`
- Delete: `resources/js/components/breadcrumbs.tsx`
- Delete: `resources/js/components/nav-main.tsx`
- Delete: `resources/js/components/nav-user.tsx`
- Delete: `resources/js/components/nav-footer.tsx`
- Delete: `resources/js/components/user-menu-content.tsx`
- Delete: `resources/js/components/user-info.tsx`
- Delete: `resources/js/components/appearance-tabs.tsx`
- Delete: `resources/js/components/heading.tsx`
- Delete: `resources/js/components/delete-user.tsx`
- Delete: `resources/js/components/password-input.tsx`
- Delete: `resources/js/components/text-link.tsx`
- Delete: `resources/js/components/input-error.tsx`
- Delete: `resources/js/components/alert-error.tsx`
- Delete: `resources/js/layouts/app/app-sidebar-layout.tsx`
- Delete: `resources/js/layouts/app/app-header-layout.tsx`
- Delete: `resources/js/layouts/auth/auth-card-layout.tsx`
- Delete: `resources/js/layouts/auth/auth-split-layout.tsx`

- [ ] **Step 1: Delete old shadcn/ui components**

```bash
rm -rf resources/js/components/ui/
rm components.json
rm resources/js/components/app-shell.tsx resources/js/components/app-content.tsx resources/js/components/app-sidebar.tsx resources/js/components/app-sidebar-header.tsx resources/js/components/app-header.tsx resources/js/components/breadcrumbs.tsx resources/js/components/nav-main.tsx resources/js/components/nav-user.tsx resources/js/components/nav-footer.tsx resources/js/components/user-menu-content.tsx resources/js/components/user-info.tsx resources/js/components/appearance-tabs.tsx resources/js/components/heading.tsx resources/js/components/delete-user.tsx resources/js/components/password-input.tsx resources/js/components/text-link.tsx resources/js/components/input-error.tsx resources/js/components/alert-error.tsx
rm resources/js/layouts/app/app-sidebar-layout.tsx resources/js/layouts/app/app-header-layout.tsx
rm resources/js/layouts/auth/auth-card-layout.tsx resources/js/layouts/auth/auth-split-layout.tsx
```

- [ ] **Step 2: Keep essential components**

These files should remain:
- `resources/js/components/app-logo.tsx`
- `resources/js/components/app-logo-icon.tsx`
- `resources/js/lib/utils.ts` (cn utility still useful)

- [ ] **Step 3: Verify types check**

```bash
pnpm run types:check
```

Fix any remaining import errors.

- [ ] **Step 4: Verify build**

```bash
pnpm run build
```

Expected: Build succeeds with TailAdmin theme active.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore: remove shadcn/ui components, complete TailAdmin integration"
```

---

### Task 13: Final Verification

- [ ] **Step 1: Run dev server**

```bash
pnpm run dev
```

- [ ] **Step 2: Verify in browser**

Check:
- Login page renders with TailAdmin styling
- Dashboard shows TailAdmin sidebar, header, search bar
- Sidebar collapses/expands on hover
- Dark mode toggle works
- Mobile responsive sidebar works
- User dropdown shows logged-in user info

- [ ] **Step 3: Run type check one more time**

```bash
pnpm run types:check
```

- [ ] **Step 4: Final commit if needed**

```bash
git add -A
git commit -m "fix: final adjustments for TailAdmin integration"
```
