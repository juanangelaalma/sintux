import { Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";
import { Dropdown } from "@/components/ui/dropdown/Dropdown";
import { DropdownItem } from "@/components/ui/dropdown/DropdownItem";
import type { Auth } from "@/types";

type PageProps = {
  auth?: Auth;
};

const menuItems = [
  {
    href: "/settings/profile",
    label: "Edit profile",
    icon: (
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z"
      />
    ),
  },
  {
    href: "/settings/profile",
    label: "Account settings",
    icon: (
      <path
        fillRule="evenodd"
        clipRule="evenodd"
        d="M10.4858 3.5L13.5182 3.5C13.9233 3.5 14.2518 3.82851 14.2518 4.23377C14.2518 5.9529 16.1129 7.02795 17.602 6.1682C17.9528 5.96567 18.4014 6.08586 18.6039 6.43667L20.1203 9.0631C20.3229 9.41407 20.2027 9.86286 19.8517 10.0655C18.3625 10.9253 18.3625 13.0747 19.8517 13.9345C20.2026 14.1372 20.3229 14.5859 20.1203 14.9369L18.6039 17.5634C18.4013 17.9142 17.9528 18.0344 17.602 17.8318C16.1129 16.9721 14.2518 18.0471 14.2518 19.7663C14.2518 20.1715 13.9233 20.5 13.5182 20.5H10.4858C10.0804 20.5 9.75182 20.1714 9.75182 19.766C9.75182 18.0461 7.88983 16.9717 6.40067 17.8314C6.04945 18.0342 5.60037 17.9139 5.39767 17.5628L3.88167 14.937C3.67903 14.586 3.79928 14.1372 4.15026 13.9346C5.63949 13.0748 5.63946 10.9253 4.15025 10.0655C3.79926 9.86282 3.67901 9.41401 3.88165 9.06303L5.39764 6.43725C5.60034 6.08617 6.04943 5.96581 6.40065 6.16858C7.88982 7.02836 9.75182 5.9539 9.75182 4.23399C9.75182 3.82862 10.0804 3.5 10.4858 3.5ZM13.5182 2L10.4858 2C9.25201 2 8.25182 3.00019 8.25182 4.23399C8.25182 4.79884 7.64013 5.15215 7.15065 4.86955C6.08213 4.25263 4.71559 4.61859 4.0986 5.68725L2.58261 8.31303C1.96575 9.38146 2.33183 10.7477 3.40025 11.3645C3.88948 11.647 3.88947 12.3531 3.40026 12.6355C2.33184 13.2524 1.96578 14.6186 2.58263 15.687L4.09863 18.3128C4.71562 19.3814 6.08215 19.7474 7.15067 19.1305C7.64015 18.8479 8.25182 19.2012 8.25182 19.766C8.25182 20.9998 9.25201 22 10.4858 22H13.5182C14.7519 22 15.7518 20.9998 15.7518 19.7663C15.7518 19.2015 16.3632 18.8487 16.852 19.1309C17.9202 19.7476 19.2862 19.3816 19.9029 18.3134L21.4193 15.6869C22.0361 14.6185 21.6701 13.2523 20.6017 12.6355C20.1125 12.3531 20.1125 11.647 20.6017 11.3645C21.6701 10.7477 22.0362 9.38152 21.4193 8.3131L19.903 5.68667C19.2862 4.61842 17.9202 4.25241 16.852 4.86917C16.3632 5.15138 15.7518 4.79878 15.7518 4.23399C15.7518 3.00019 14.7519 2 13.5182 2Z"
      />
    ),
  },
];

const itemClasses =
  "flex items-center gap-3 px-3 py-2 font-medium text-gray-700 rounded-lg group text-theme-sm hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300";

export default function UserDropdown() {
  const [isOpen, setIsOpen] = useState(false);
  const { auth } = usePage<PageProps>().props;
  const user = auth?.user;
  const tenant = auth?.tenant;
  const branch = auth?.branch;
  const branches = auth?.branches ?? [];
  const branchScope = auth?.branch_scope;
  const isAllScope = branchScope === "all";
  const hasHqBranch = auth?.is_hq || branches.some((b) => b.is_headquarters);
  const showAllOption = !hasHqBranch && branches.length > 1;
  const showBranchSwitcher = branches.length > 1;

  if (!user) {
    return null;
  }

  const displayName = user.name.charAt(0).toUpperCase() + user.name.slice(1);
  const initial = user.name.charAt(0).toUpperCase();

  function toggleDropdown() {
    setIsOpen((prev) => !prev);
  }

  function closeDropdown() {
    setIsOpen(false);
  }

  function handleSwitchAll() {
    if (isAllScope && !branch) {
      closeDropdown();

      return;
    }

    router.post("/company/branches/switch", { scope: "all" }, {
      preserveScroll: true,
      onSuccess: closeDropdown,
    });
  }

  function handleSwitchBranch(b: { id: number; is_headquarters?: boolean }) {
    if (b.is_headquarters) {
      if (isAllScope && branch?.id === b.id) {
        closeDropdown();

        return;
      }

      router.post("/company/branches/switch", { scope: "all", branch_id: b.id }, {
        preserveScroll: true,
        onSuccess: closeDropdown,
      });

      return;
    }

    if (!isAllScope && branch?.id === b.id) {
      closeDropdown();

      return;
    }

    router.post("/company/branches/switch", { scope: "branch", branch_id: b.id }, {
      preserveScroll: true,
      onSuccess: closeDropdown,
    });
  }

  return (
    <div className="relative">
      <button
        onClick={toggleDropdown}
        aria-haspopup="true"
        aria-expanded={isOpen}
        className="flex items-center text-gray-700 dropdown-toggle dark:text-gray-400"
      >
        <span className="mr-3 overflow-hidden rounded-full h-11 w-11 bg-gray-200 flex items-center justify-center">
          <span className="text-sm font-medium text-gray-600">{initial}</span>
        </span>

        <span className="mr-1 flex flex-col items-start">
          <span className="block font-medium text-theme-sm leading-tight">{displayName}</span>
          {(tenant?.name || branch?.name) && (
            <span className="block text-theme-xs leading-tight text-gray-500 dark:text-gray-400">
              {tenant?.name}
              {branch?.name ? ` | ${branch.name}` : ""}
            </span>
          )}
        </span>

        <svg
          className={`stroke-gray-500 dark:stroke-gray-400 transition-transform duration-200 ${
            isOpen ? "rotate-180" : ""
          }`}
          width="18"
          height="20"
          viewBox="0 0 18 20"
          fill="none"
          aria-hidden="true"
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
            {user.name}
          </span>
          <span className="mt-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">
            {user.email}
          </span>
        </div>

        {showBranchSwitcher && (
          <div className="pt-3 pb-3 border-b border-gray-200 dark:border-gray-800">
            <span className="block px-1 pb-2 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
              Switch branch
            </span>
            <ul className="flex flex-col gap-1">
              {showAllOption && (
                <li>
                  <button
                    type="button"
                    onClick={handleSwitchAll}
                    className={`${itemClasses} w-full justify-between ${
                      isAllScope && !branch
                        ? "bg-brand-50 text-brand-700 dark:bg-white/5 dark:text-brand-300"
                        : ""
                    }`}
                  >
                    <span className="flex items-center gap-3">
                      <svg
                        className="fill-gray-500 dark:fill-gray-400"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                      >
                        <path
                          fillRule="evenodd"
                          clipRule="evenodd"
                          d="M11.25 4.75C11.25 3.7835 10.4665 3 9.5 3H4C3.0335 3 2.25 3.7835 2.25 4.75V12C2.25 12.9665 3.0335 13.75 4 13.75H9.5C10.4665 13.75 11.25 12.9665 11.25 12V4.75ZM3.75 5C3.75 4.86193 3.86193 4.75 4 4.75H9.5C9.63807 4.75 9.75 4.86193 9.75 5V12C9.75 12.1381 9.63807 12.25 9.5 12.25H4C3.86193 12.25 3.75 12.1381 3.75 12V5ZM21.75 5C21.75 4.0335 20.9665 3.25 20 3.25H14.5C13.5335 3.25 12.75 4.0335 12.75 5V12C12.75 12.9665 13.5335 13.75 14.5 13.75H20C20.9665 13.75 21.75 12.9665 21.75 12V5ZM20 4.75C20.1381 4.75 20.25 4.86193 20.25 5V12C20.25 12.1381 20.1381 12.25 20 12.25H14.5C14.3619 12.25 14.25 12.1381 14.25 12V5C14.25 4.86193 14.3619 4.75 14.5 4.75H20ZM11.25 16C11.25 15.0335 10.4665 14.25 9.5 14.25H4C3.0335 14.25 2.25 15.0335 2.25 16V19.25C2.25 20.2165 3.0335 21 4 21H9.5C10.4665 21 11.25 20.2165 11.25 19.25V16ZM3.75 16.25C3.75 16.1119 3.86193 16 4 16H9.5C9.63807 16 9.75 16.1119 9.75 16.25V19.25C9.75 19.3881 9.63807 19.5 9.5 19.5H4C3.86193 19.5 3.75 19.3881 3.75 19.25V16.25ZM21.75 16C21.75 15.0335 20.9665 14.25 20 14.25H14.5C13.5335 14.25 12.75 15.0335 12.75 16V19.25C12.75 20.2165 13.5335 21 14.5 21H20C20.9665 21 21.75 20.2165 21.75 19.25V16ZM20 15.75C20.1381 15.75 20.25 15.8619 20.25 16V19.25C20.25 19.3881 20.1381 19.5 20 19.5H14.5C14.3619 19.5 14.25 19.3881 14.25 15.75H20Z"
                        />
                      </svg>
                      Semua cabang
                    </span>
                  </button>
                </li>
              )}
              {branches.map((b) => (
                <li key={b.id}>
                  <button
                    type="button"
                    onClick={() => handleSwitchBranch(b)}
                    className={`${itemClasses} w-full justify-between ${
                      (isAllScope && b.is_headquarters) || (!isAllScope && branch?.id === b.id)
                        ? "bg-brand-50 text-brand-700 dark:bg-white/5 dark:text-brand-300"
                        : ""
                    }`}
                  >
                    <span className="flex items-center gap-3">
                      <svg
                        className="fill-gray-500 dark:fill-gray-400"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        aria-hidden="true"
                      >
                        <path
                          fillRule="evenodd"
                          clipRule="evenodd"
                          d="M4 3.25C3.0335 3.25 2.25 4.0335 2.25 5V19C2.25 19.9665 3.0335 20.75 4 20.75H20C20.9665 20.75 21.75 19.9665 21.75 19V5C21.75 4.0335 20.9665 3.25 20 3.25H4ZM4 4.75C3.86193 4.75 3.75 4.86193 3.75 5V6.25H20.25V5C20.25 4.86193 20.1381 4.75 20 4.75H4ZM20.25 7.75H3.75V19C3.75 19.1381 3.86193 19.25 4 19.25H20C20.1381 19.25 20.25 19.1381 20.25 19V7.75ZM6.75 9.25C6.33579 9.25 6 9.58579 6 10V15C6 15.4142 6.33579 15.75 6.75 15.75H9.75C10.1642 15.75 10.5 15.4142 10.5 15V10C10.5 9.58579 10.1642 9.25 9.75 9.25H6.75ZM7.5 10.75V14.25H9V10.75H7.5ZM14.25 9.25C13.8358 9.25 13.5 9.58579 13.5 10V15C13.5 15.4142 13.8358 15.75 14.25 15.75H17.25C17.6642 15.75 18 15.4142 18 15V10C18 9.58579 17.6642 9.25 17.25 9.25H14.25ZM15 10.75V14.25H16.5V10.75H15Z"
                        />
                      </svg>
                      {b.name}
                    </span>
                    <span className="text-theme-xs text-gray-400 dark:text-gray-500">
                      {b.code}
                    </span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}

        <ul className="flex flex-col gap-1 pt-4 pb-3 border-b border-gray-200 dark:border-gray-800">
          {menuItems.map((item) => (
            <li key={item.href + item.label}>
              <DropdownItem
                onItemClick={closeDropdown}
                tag="a"
                href={item.href}
                className={itemClasses}
              >
                <svg
                  className="fill-gray-500 group-hover:fill-gray-700 dark:fill-gray-400 dark:group-hover:fill-gray-300"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none"
                  aria-hidden="true"
                >
                  {item.icon}
                </svg>
                {item.label}
              </DropdownItem>
            </li>
          ))}
        </ul>

        <Link
          href="/logout"
          method="post"
          as="button"
          className={`${itemClasses} mt-3`}
        >
          <svg
            className="fill-gray-500 group-hover:fill-gray-700 dark:group-hover:fill-gray-300"
            width="24"
            height="24"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
          >
            <path
              fillRule="evenodd"
              clipRule="evenodd"
              d="M15.1007 19.247C14.6865 19.247 14.3507 18.9112 14.3507 18.497L14.3507 14.245H12.8507V18.497C12.8507 19.7396 13.8581 20.747 15.1007 20.747H18.5007C19.7434 20.747 20.7507 19.7396 20.7507 18.497L20.7507 5.49609C20.7507 4.25345 19.7433 3.24609 18.5007 3.24609H15.1007C13.8581 3.24609 12.8507 4.25345 12.8507 5.49609V9.74501L14.3507 9.74501V5.49609C14.3507 5.08188 14.6865 4.74609 15.1007 4.74609L18.5007 4.74609C18.9149 4.74609 19.2507 5.08188 19.2507 5.49609L19.2507 18.497C19.2507 18.9112 18.9149 19.247 18.5007 19.247H15.1007ZM3.25073 11.9984C3.25073 12.2144 3.34204 12.4091 3.48817 12.546L8.09483 17.1556C8.38763 17.4485 8.86251 17.4487 9.15549 17.1559C9.44848 16.8631 9.44863 16.3882 9.15583 16.0952L5.81116 12.7484L16.0007 12.7484C16.4149 12.7484 16.7507 12.4127 16.7507 11.9984C16.7507 11.5842 16.4149 11.2484 16.0007 11.2484L5.81528 11.2484L9.15585 7.90554C9.44864 7.61255 9.44847 7.13767 9.15547 6.84488C8.86248 6.55209 8.3876 6.55226 8.09481 6.84525L3.52309 11.4202C3.35673 11.5577 3.25073 11.7657 3.25073 11.9984Z"
            />
          </svg>
          Sign out
        </Link>
      </Dropdown>
    </div>
  );
}