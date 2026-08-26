import type { ReactNode } from 'react';

export type DataTableColumn<T> = {
    key: string;
    header: ReactNode;
    render: (row: T) => ReactNode;
    align?: 'left' | 'right';
    cellClassName?: string;
};

export type DataTablePagination = {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    emptyMessage: ReactNode;
    getRowKey: (row: T) => string | number;
    rows: T[];
    pagination?: DataTablePagination;
    onPageChange?: (page: number) => void;
};

function getPageWindow(current: number, last: number): (number | 'ellipsis')[] {
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const candidates = new Set([1, last, current - 1, current, current + 1]);
    const sorted = [...candidates]
        .filter((p) => p >= 1 && p <= last)
        .sort((a, b) => a - b);
    const window: (number | 'ellipsis')[] = [];
    let previous = 0;

    for (const page of sorted) {
        if (page - previous > 1) {
            window.push('ellipsis');
        }
        window.push(page);
        previous = page;
    }

    return window;
}

export default function DataTable<T>({
    columns,
    emptyMessage,
    getRowKey,
    rows,
    pagination,
    onPageChange,
}: DataTableProps<T>) {
    const hasPagination = Boolean(
        pagination && pagination.lastPage > 1 && onPageChange,
    );
    const start =
        pagination && pagination.total > 0
            ? (pagination.currentPage - 1) * pagination.perPage + 1
            : 0;
    const end = pagination
        ? Math.min(
              pagination.currentPage * pagination.perPage,
              pagination.total,
          )
        : 0;

    const pageButtonClass = (active: boolean) =>
        `inline-flex min-w-8 items-center justify-center rounded-md px-2 py-1.5 text-theme-sm font-medium transition-colors ${
            active
                ? 'bg-indigo-600 text-white'
                : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-white/[0.05]'
        }`;

    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/[0.05] dark:bg-white/[0.03]">
            <div className="max-w-full overflow-x-auto">
                <table className="min-w-full">
                    <thead className="border-b border-gray-100 dark:border-white/[0.05]">
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    className={`px-5 py-3 text-theme-xs font-medium whitespace-nowrap text-gray-500 dark:text-gray-400 ${
                                        column.align === 'right'
                                            ? 'text-right'
                                            : 'text-start'
                                    }`}
                                >
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        {rows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-5 py-10 text-center text-theme-sm text-gray-500 dark:text-gray-400"
                                >
                                    {emptyMessage}
                                </td>
                            </tr>
                        ) : (
                            rows.map((row) => (
                                <tr key={getRowKey(row)}>
                                    {columns.map((column) => (
                                        <td
                                            key={column.key}
                                            className={`px-4 py-3 text-theme-sm whitespace-nowrap ${
                                                column.align === 'right'
                                                    ? 'text-right'
                                                    : 'text-start'
                                            } ${column.cellClassName ?? 'text-gray-500 dark:text-gray-400'}`}
                                        >
                                            {column.render(row)}
                                        </td>
                                    ))}
                                </tr>
                            ))
                        )}
                    </tbody>
                </table>
            </div>

            {hasPagination && pagination && onPageChange && (
                <div className="flex flex-col gap-3 border-t border-gray-100 px-5 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-white/[0.05]">
                    <p className="text-theme-sm text-gray-500 dark:text-gray-400">
                        Menampilkan {start}–{end} dari {pagination.total}
                    </p>
                    <nav
                        className="flex items-center gap-1"
                        aria-label="Pagination"
                    >
                        <button
                            type="button"
                            onClick={() =>
                                onPageChange(pagination.currentPage - 1)
                            }
                            disabled={pagination.currentPage <= 1}
                            className={pageButtonClass(false)}
                            aria-label="Halaman sebelumnya"
                        >
                            «
                        </button>
                        {getPageWindow(
                            pagination.currentPage,
                            pagination.lastPage,
                        ).map((item, index) =>
                            item === 'ellipsis' ? (
                                <span
                                    key={`ellipsis-${index}`}
                                    className="px-2 text-gray-400"
                                >
                                    …
                                </span>
                            ) : (
                                <button
                                    key={item}
                                    type="button"
                                    onClick={() => onPageChange(item)}
                                    disabled={item === pagination.currentPage}
                                    className={pageButtonClass(
                                        item === pagination.currentPage,
                                    )}
                                >
                                    {item}
                                </button>
                            ),
                        )}
                        <button
                            type="button"
                            onClick={() =>
                                onPageChange(pagination.currentPage + 1)
                            }
                            disabled={
                                pagination.currentPage >= pagination.lastPage
                            }
                            className={pageButtonClass(false)}
                            aria-label="Halaman berikutnya"
                        >
                            »
                        </button>
                    </nav>
                </div>
            )}
        </div>
    );
}
