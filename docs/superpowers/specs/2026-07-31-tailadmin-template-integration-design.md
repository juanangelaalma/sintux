# TailAdmin Template Integration Design

## Overview

Integrate the TailAdmin React admin dashboard template (`free-react-tailwind-admin-dashboard-main/`) into the existing Laravel + Inertia.js + React project. Replace shadcn/ui design system with TailAdmin's design system. Adapt all template components from react-router to Inertia.js.

## Decisions

- **Approach**: Adapt template to Inertia.js (not hybrid/adapter layer)
- **Design system**: Replace shadcn/ui with TailAdmin (brand colors, Outfit font, custom utilities)
- **Scope**: Layout, sidebar, header, structural components, icons. No demo pages.
- **Dependencies**: Only install what's needed now. Charts/calendar libraries deferred.

## Section 1: CSS & Theme

Replace `resources/css/app.css` contents with TailAdmin's design system from `free-react-tailwind-admin-dashboard-main/src/index.css`.

Changes:
- Font: `Outfit` (replace `Instrument Sans`)
- Color tokens: `--color-brand-*`, `--color-gray-*`, `--color-success-*`, `--color-error-*`, `--color-warning-*` (replace oklch CSS variables)
- Custom utilities: `menu-item`, `menu-item-active`, `menu-item-inactive`, `menu-item-icon-size`, `menu-dropdown-item`, `no-scrollbar`, `custom-scrollbar`, etc.
- Dark mode: `@custom-variant dark (&:is(.dark *))`
- Shadows: `shadow-theme-xs/sm/md/lg/xl`, `shadow-focus-ring`, etc.
- Remove: all shadcn/ui CSS variables (`--background`, `--foreground`, `--card`, `--primary`, etc.)
- Remove: `@import 'tw-animate-css'`

## Section 2: Layout System

### Files to create/replace

| Action | Path | Source |
|--------|------|--------|
| Replace | `resources/js/layouts/app-layout.tsx` | Adapt template `AppLayout.tsx` |
| Delete | `resources/js/layouts/app/app-sidebar-layout.tsx` | - |
| Delete | `resources/js/layouts/app/app-header-layout.tsx` | - |
| Create | `resources/js/layouts/AppSidebar.tsx` | Adapt template `AppSidebar.tsx` |
| Create | `resources/js/layouts/AppHeader.tsx` | Adapt template `AppHeader.tsx` |
| Create | `resources/js/layouts/Backdrop.tsx` | Copy template `Backdrop.tsx` |
| Create | `resources/js/layouts/SidebarWidget.tsx` | Copy template `SidebarWidget.tsx` |
| Create | `resources/js/context/SidebarContext.tsx` | Copy template `SidebarContext.tsx` |

### Adaptation rules

- `import { Link, useLocation } from "react-router"` → `import { Link, usePage } from '@inertiajs/react'`
- `useLocation().pathname` → `usePage().url`
- `<Outlet />` → `{children}` prop
- `useNavigate()` → `router.visit()` from `@inertiajs/react`
- All `<Link to="...">` → `<Link href="...">`

## Section 3: Icons

Copy `free-react-tailwind-admin-dashboard-main/src/icons/` to `resources/js/icons/`.

Install `vite-plugin-svgr` and add to `vite.config.ts`:
```ts
import svgr from 'vite-plugin-svgr';
// add to plugins array
```

Update SVG imports to use `?react` suffix (Vite SVGR convention).

## Section 4: Dependencies

### Add to package.json

**dependencies:**
- (none new — `clsx`, `tailwind-merge` already present)

**devDependencies:**
- `vite-plugin-svgr`

### Remove from package.json (shadcn/ui related)
- `@radix-ui/react-avatar`
- `@radix-ui/react-checkbox`
- `@radix-ui/react-collapsible`
- `@radix-ui/react-dialog`
- `@radix-ui/react-dropdown-menu`
- `@radix-ui/react-label`
- `@radix-ui/react-navigation-menu`
- `@radix-ui/react-select`
- `@radix-ui/react-separator`
- `@radix-ui/react-slot`
- `@radix-ui/react-toggle`
- `@radix-ui/react-toggle-group`
- `@radix-ui/react-tooltip`
- `class-variance-authority`
- `sonner`
- `tw-animate-css`

### Deferred (add when needed)
- `apexcharts`, `react-apexcharts`
- `@fullcalendar/*`
- `flatpickr`
- `swiper`
- `react-dnd`, `react-dnd-html5-backend`
- `react-dropzone`
- `react-helmet-async`
- `@react-jvectormap/*`

## Section 5: Components

### From template → `resources/js/components/`

| Source | Destination |
|--------|-------------|
| `components/common/ThemeToggleButton.tsx` | `resources/js/components/common/ThemeToggleButton.tsx` |
| `components/header/NotificationDropdown.tsx` | `resources/js/components/header/NotificationDropdown.tsx` |
| `components/header/UserDropdown.tsx` | `resources/js/components/header/UserDropdown.tsx` |

These components use react-router `Link` — adapt to Inertia `Link`.

### Keep (adapt, don't replace)
- `resources/js/components/app-logo.tsx` — update to use template logo path
- `resources/js/components/app-logo-icon.tsx` — same

## Section 5b: Static Assets

Copy template's `public/images/logo/` to project's `public/images/logo/`:
- `logo.svg` (light)
- `logo-dark.svg` (dark)
- `logo-icon.svg` (collapsed sidebar)

These are referenced by `AppSidebar.tsx` and `AppHeader.tsx`.

## Section 6: Cleanup

### Delete
- `resources/js/components/ui/` (entire directory — shadcn/ui)
- `components.json`
- `resources/js/layouts/auth/` (if it uses shadcn/ui)
- `resources/js/layouts/settings/` (if it uses shadcn/ui)

### Update
- `resources/js/app.tsx`: remove `TooltipProvider`, `Toaster`, shadcn imports. Simplify layout routing.
- `resources/js/pages/dashboard.tsx`: remove shadcn `PlaceholderPattern` import, use simple Tailwind layout
- `resources/js/pages/welcome.tsx`: remove shadcn imports
- `resources/js/pages/auth/`: update to use template's auth page style or simple Tailwind
- `resources/js/pages/settings/`: update imports

### Auth layout
Keep `resources/js/layouts/auth-layout.tsx` but simplify — remove shadcn dependencies.

## File tree after integration

```
resources/
├── css/
│   └── app.css                    (TailAdmin theme)
├── js/
│   ├── app.tsx                    (simplified, no shadcn)
│   ├── components/
│   │   ├── common/
│   │   │   └── ThemeToggleButton.tsx
│   │   ├── header/
│   │   │   ├── NotificationDropdown.tsx
│   │   │   └── UserDropdown.tsx
│   │   ├── app-logo.tsx
│   │   └── app-logo-icon.tsx
│   ├── context/
│   │   └── SidebarContext.tsx
│   ├── hooks/
│   │   └── use-appearance.ts      (keep)
│   ├── icons/
│   │   ├── index.ts
│   │   └── *.svg
│   ├── layouts/
│   │   ├── app-layout.tsx         (TailAdmin layout)
│   │   ├── auth-layout.tsx        (simplified)
│   │   ├── AppSidebar.tsx
│   │   ├── AppHeader.tsx
│   │   ├── Backdrop.tsx
│   │   └── SidebarWidget.tsx
│   ├── pages/
│   │   ├── dashboard.tsx
│   │   ├── welcome.tsx
│   │   ├── auth/
│   │   └── settings/
│   ├── routes/
│   └── types/
```

## Non-goals

- Demo pages (Calendar, Charts, Forms, Tables, UI Elements) — not included
- Third-party chart/calendar libraries — deferred
- Auth page redesign — keep functional, just remove shadcn deps
- Settings page redesign — same
