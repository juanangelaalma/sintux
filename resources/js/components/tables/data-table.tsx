import type { ReactNode } from 'react';

export type DataTableColumn<T> = {
    key: string;
    header: ReactNode;
    render: (row: T) => ReactNode;
    align?: 'left' | 'right';
    cellClassName?: string;
};

type DataTableProps<T> = {
    columns: DataTableColumn<T>[];
    emptyMessage: ReactNode;
    getRowKey: (row: T) => string | number;
    rows: T[];
};

export default function DataTable<T>({
    columns,
    emptyMessage,
    getRowKey,
    rows,
}: DataTableProps<T>) {
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
        </div>
    );
}
