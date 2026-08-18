import { Link, usePage } from "@inertiajs/react";
import { useCallback, useMemo, useState } from "react";
import { useSidebar } from "@/context/SidebarContext";
import {
  BoxIcon,
  BoxCubeIcon,
  GridIcon,
  GroupIcon,
  HorizontaLDots,
  ListIcon,
  UserIcon,
} from "@/icons";

type LeafSubItem = {
  name: string;
  path: string;
  permission?: string;
};

type GroupSubItem = {
  name: string;
  permission?: string;
  subItems: LeafSubItem[];
};

type SubItem = LeafSubItem | GroupSubItem;

type NavItem = {
  name: string;
  icon: React.ReactNode;
  path?: string;
  permission?: string;
  subItems?: SubItem[];
};

const companyNavItems: NavItem[] = [
  {
    icon: <GridIcon />,
    name: "Dashboard",
    path: "/dashboard",
  },
  {
    icon: <UserIcon />,
    name: "Users",
    path: "/company/users",
    permission: "company.user.manage",
  },
  {
    icon: <BoxIcon />,
    name: "Branches",
    path: "/company/branches",
    permission: "company.branch.manage",
  },
  {
    icon: <GroupIcon />,
    name: "Contact",
    subItems: [
      { name: "Customer", path: "/company/contacts/customers" },
      { name: "Supplier", path: "/company/contacts/suppliers" },
      { name: "Employee", path: "/company/contacts/employees" },
    ],
  },
  {
    icon: <ListIcon />,
    name: "Chart of Accounts",
    path: "/accounting/chart-of-accounts",
    permission: "accounting.account.view",
  },
  {
    icon: <BoxCubeIcon />,
    name: "Produk",
    path: "/product",
    permission: "product.view",
  },
  {
    icon: <BoxIcon />,
    name: "Purchasing",
    path: "/purchasing/orders",
    permission: "purchasing.po.view",
  },
];

function isGroupSubItem(sub: SubItem): sub is GroupSubItem {
  return "subItems" in sub && Array.isArray((sub as GroupSubItem).subItems);
}

function hasPermission(item: { permission?: string; subItems?: SubItem[] }, perms: string[]): boolean {
  if (item.subItems && item.subItems.length > 0) {
    return item.subItems.some((child) => hasPermission(child, perms));
  }
  return !item.permission || perms.includes(item.permission);
}

const CompanySidebar: React.FC = () => {
  const { isExpanded, isMobileOpen, isHovered, setIsHovered } = useSidebar();
  const { url, props } = usePage();

  const auth = (props.auth ?? {}) as { permissions?: string[] };
  const perms = useMemo(() => auth.permissions ?? [], [auth.permissions]);

  const visibleNavItems = useMemo(() => {
    return companyNavItems.filter((n) => hasPermission(n, perms));
  }, [perms]);

  const [manualSubmenu, setManualSubmenu] = useState<{
    type: "main";
    index: number;
  } | null>(null);

  const [manualSubGroups, setManualSubGroups] = useState<Record<string, boolean>>({});

  const [prevUrl, setPrevUrl] = useState(url);

  if (prevUrl !== url) {
    setPrevUrl(url);
    setManualSubmenu(null);
    setManualSubGroups({});
  }

  const isActive = useCallback(
    (path?: string) => Boolean(path && url === path),
    [url]
  );

  const autoOpenSubmenu = useMemo(() => {
    let matched: { type: "main"; index: number } | null = null;
    visibleNavItems.forEach((nav, index) => {
      if (nav.subItems) {
        nav.subItems.forEach((subItem) => {
          if (isGroupSubItem(subItem)) {
            subItem.subItems.forEach((leaf) => {
              if (isActive(leaf.path)) {
                matched = { type: "main", index };
              }
            });
          } else if (isActive(subItem.path)) {
            matched = { type: "main", index };
          }
        });
      }
    });

    return matched;
  }, [isActive, visibleNavItems]);

  const openSubmenu = manualSubmenu ?? autoOpenSubmenu;

  const handleSubmenuToggle = (index: number, menuType: "main") => {
    setManualSubmenu((prev) => {
      if (prev && prev.type === menuType && prev.index === index) {
        return null;
      }
      return { type: menuType, index };
    });
  };

  const isSubGroupOpen = (groupKey: string, group: GroupSubItem) => {
    if (manualSubGroups[groupKey] !== undefined) {
      return manualSubGroups[groupKey];
    }
    return group.subItems.some((leaf) => isActive(leaf.path));
  };

  const toggleSubGroup = (groupKey: string, group: GroupSubItem) => {
    const currentState = isSubGroupOpen(groupKey, group);
    setManualSubGroups((prev) => ({
      ...prev,
      [groupKey]: !currentState,
    }));
  };

  const renderSubItem = (sub: SubItem, parentName: string, permissions: string[]) => {
    if (isGroupSubItem(sub)) {
      const visibleLeaves = sub.subItems.filter(
        (leaf) => !leaf.permission || permissions.includes(leaf.permission)
      );

      if (visibleLeaves.length === 0) return null;

      const groupKey = `${parentName}-${sub.name}`;
      const open = isSubGroupOpen(groupKey, sub);

      return (
        <li key={sub.name} className="flex flex-col gap-1">
          <button
            type="button"
            onClick={() => toggleSubGroup(groupKey, sub)}
            className="flex w-full items-center justify-between py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          >
            <span>{sub.name}</span>
            <svg
              className={`size-3.5 transition-transform duration-200 ${
                open ? "rotate-180" : ""
              }`}
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

          {open && (
            <ul className="flex flex-col gap-1 pl-3 border-l border-gray-200 dark:border-gray-800">
              {visibleLeaves.map((leaf) => (
                <li key={leaf.name}>
                  <Link
                    href={leaf.path}
                    className={`block py-1 text-sm font-medium transition-colors ${
                      isActive(leaf.path)
                        ? "text-brand-500 font-semibold"
                        : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
                    }`}
                  >
                    {leaf.name}
                  </Link>
                </li>
              ))}
            </ul>
          )}
        </li>
      );
    }

    if (sub.permission && !permissions.includes(sub.permission)) {
      return null;
    }

    return (
      <li key={sub.name}>
        <Link
          href={sub.path}
          className={`block py-1 text-sm font-medium transition-colors ${
            isActive(sub.path)
              ? "text-brand-500 font-semibold"
              : "text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
          }`}
        >
          {sub.name}
        </Link>
      </li>
    );
  };

  const renderMenuItems = (items: NavItem[], menuType: "main", permissions: string[]) => (
    <ul className="flex flex-col gap-4">
      {items.map((nav, index) => (
        <li key={nav.name}>
          {nav.subItems ? (
            <>
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
                  className={`menu-item-icon-size ${
                    openSubmenu?.type === menuType && openSubmenu?.index === index
                      ? "menu-item-icon-active"
                      : "menu-item-icon-inactive"
                  }`}
                >
                  {nav.icon}
                </span>
                {(isExpanded || isHovered || isMobileOpen) && (
                  <>
                    <span className="menu-item-text">{nav.name}</span>
                    <svg
                      className={`ml-auto size-4 transition-transform duration-200 ${
                        openSubmenu?.type === menuType && openSubmenu?.index === index
                          ? "rotate-180"
                          : ""
                      }`}
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
                  </>
                )}
              </button>

              {openSubmenu?.type === menuType &&
                openSubmenu?.index === index &&
                (isExpanded || isHovered || isMobileOpen) && (
                  <ul className="mt-2 flex flex-col gap-2 pl-9">
                    {nav.subItems.map((sub) =>
                      renderSubItem(sub, nav.name, permissions)
                    )}
                  </ul>
                )}
            </>
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
        <Link href="/dashboard">
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
                  "Company Menu"
                ) : (
                  <HorizontaLDots className="size-6" />
                )}
              </h2>
              {renderMenuItems(visibleNavItems, "main", perms)}
            </div>
          </div>
        </nav>
      </div>
    </aside>
  );
};

export default CompanySidebar;
