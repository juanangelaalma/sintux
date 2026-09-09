import {
    Button,
    Checkbox,
    EmptyState,
    ListBox,
    Pagination,
    Select,
    Table,
} from '@heroui/react';
import type { SortDescriptor } from '@heroui/react';
import { Icon } from '@iconify/react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

export type DataTableColumn<T> = {
    key: string;
    header: ReactNode;
    render: (row: T) => ReactNode;
    align?: 'left' | 'right';
    allowsSorting?: boolean;
    isRowHeader?: boolean;
    cellClassName?: string;
    copyableKey?: (row: T) => string;
};

export type DataTablePagination = {
    currentPage: number;
    lastPage: number;
    total: number;
    perPage: number;
};

type HerouiDataTableProps<T> = {
    columns: DataTableColumn<T>[];
    emptyMessage: ReactNode;
    getRowKey: (row: T) => string | number;
    rows: T[];
    pagination?: DataTablePagination;
    onPageChange?: (page: number) => void;
    onPerPageChange?: (perPage: number) => void;
    selectable?: boolean;
    initialSortDescriptor?: SortDescriptor;
};

export default function HerouiDataTable<T>({
    columns,
    emptyMessage,
    getRowKey,
    rows,
    pagination,
    onPageChange,
    onPerPageChange,
    selectable = true,
    initialSortDescriptor,
}: HerouiDataTableProps<T>) {
    // Initial sort must match between SSR and hydration: derive it only
    // from props (this initializer runs on the server too), never from
    // window, Date, random, or locale. Unknown columns fall back to unsorted.
    const [sortDescriptor, setSortDescriptor] = useState<
        SortDescriptor | undefined
    >(() => {
        if (!initialSortDescriptor?.column) {
            return undefined;
        }

        const key = String(initialSortDescriptor.column);

        return columns.some((column) => column.key === key)
            ? { column: key, direction: initialSortDescriptor.direction }
            : undefined;
    });
    const [copiedKey, setCopiedKey] = useState<string | number | null>(null);

    const hasExplicitRowHeader = columns.some((column) => column.isRowHeader);

    const handleCopy = (text: string, key: string | number) => {
        navigator.clipboard.writeText(text);
        setCopiedKey(key);
        setTimeout(() => setCopiedKey(null), 2000);
    };

    const sortedRows = useMemo(() => {
        if (!sortDescriptor?.column) {
            return rows;
        }

        const colKey = String(sortDescriptor.column);
        const colDef = columns.find((c) => c.key === colKey);

        if (!colDef) {
            return rows;
        }

        return [...rows].sort((a, b) => {
            const valA = (a as Record<string, unknown>)[colKey];
            const valB = (b as Record<string, unknown>)[colKey];

            if (valA === valB) {
                return 0;
            }

            if (valA === null || valA === undefined) {
                return 1;
            }

            if (valB === null || valB === undefined) {
                return -1;
            }

            const cmp = String(valA).localeCompare(String(valB), undefined, {
                numeric: true,
                sensitivity: 'base',
            });

            return sortDescriptor.direction === 'descending' ? -cmp : cmp;
        });
    }, [rows, sortDescriptor, columns]);

    const hasPagination = Boolean(
        pagination && pagination.lastPage > 0 && onPageChange,
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

    return (
        <div className="space-y-3">
            <Table variant="secondary">
                <Table.ScrollContainer>
                    <Table.Content
                        aria-label="Tabel transaksi pembelian"
                        className="min-w-[800px]"
                        selectionMode={selectable ? 'multiple' : undefined}
                        sortDescriptor={sortDescriptor}
                        onSortChange={setSortDescriptor}
                    >
                        <Table.Header className="bg-cyan-500/10 text-cyan-950 dark:bg-cyan-950/40 dark:text-cyan-200">
                            {selectable && (
                                <Table.Column className="w-12 text-center">
                                    <Checkbox slot="selection" />
                                </Table.Column>
                            )}
                            {columns.map((column, index) => (
                                <Table.Column
                                    key={column.key}
                                    id={column.key}
                                    allowsSorting={column.allowsSorting ?? true}
                                    isRowHeader={
                                        column.isRowHeader ??
                                        (!hasExplicitRowHeader && index === 0)
                                    }
                                    className={`px-4 py-3 text-xs font-bold tracking-wider uppercase ${
                                        column.align === 'right'
                                            ? 'text-end'
                                            : 'text-start'
                                    }`}
                                >
                                    {({ sortDirection }) => (
                                        <Table.SortableColumnHeader
                                            sortDirection={sortDirection}
                                        >
                                            {column.header}
                                        </Table.SortableColumnHeader>
                                    )}
                                </Table.Column>
                            ))}
                        </Table.Header>
                        <Table.Body
                            renderEmptyState={() => (
                                <EmptyState className="flex min-h-[240px] w-full flex-col items-center justify-center gap-3 py-12 text-center">
                                    <div className="flex size-14 items-center justify-center rounded-full bg-surface-secondary text-muted">
                                        <Inbox className="size-7 text-muted" />
                                    </div>
                                    <span className="text-sm font-medium text-muted">
                                        {emptyMessage}
                                    </span>
                                </EmptyState>
                            )}
                        >
                            {sortedRows.map((row) => {
                                const rowKey = getRowKey(row);

                                return (
                                    <Table.Row key={rowKey} id={rowKey}>
                                        {selectable && (
                                            <Table.Cell className="w-12 text-center">
                                                <Checkbox slot="selection" />
                                            </Table.Cell>
                                        )}
                                        {columns.map((column) => {
                                            const copyText = column.copyableKey
                                                ? column.copyableKey(row)
                                                : null;

                                            return (
                                                <Table.Cell
                                                    key={column.key}
                                                    className={`px-4 py-3 text-sm whitespace-nowrap ${
                                                        column.align === 'right'
                                                            ? 'text-end'
                                                            : 'text-start'
                                                    } ${column.cellClassName ?? ''}`}
                                                >
                                                    {copyText ? (
                                                        <div className="inline-flex items-center gap-1.5">
                                                            <span>
                                                                {column.render(
                                                                    row,
                                                                )}
                                                            </span>
                                                            <Button
                                                                isIconOnly
                                                                size="sm"
                                                                variant="ghost"
                                                                aria-label="Salin nomor"
                                                                onPress={() =>
                                                                    handleCopy(
                                                                        copyText,
                                                                        rowKey,
                                                                    )
                                                                }
                                                            >
                                                                <Icon
                                                                    className={`size-3.5 transition-colors ${
                                                                        copiedKey ===
                                                                        rowKey
                                                                            ? 'text-success'
                                                                            : 'text-muted hover:text-foreground'
                                                                    }`}
                                                                    icon={
                                                                        copiedKey ===
                                                                        rowKey
                                                                            ? 'gravity-ui:check'
                                                                            : 'gravity-ui:copy'
                                                                    }
                                                                />
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        column.render(row)
                                                    )}
                                                </Table.Cell>
                                            );
                                        })}
                                    </Table.Row>
                                );
                            })}
                        </Table.Body>
                    </Table.Content>
                </Table.ScrollContainer>

                {hasPagination && pagination && (
                    <Table.Footer className="flex flex-col gap-3 border-t border-border px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3 text-xs text-muted">
                            <span>
                                Menampilkan {start}–{end} dari{' '}
                                {pagination.total} data
                            </span>
                            {onPerPageChange && (
                                <div className="flex items-center gap-1.5">
                                    <span>Tampilkan:</span>
                                    <Select
                                        value={String(pagination.perPage)}
                                        onChange={(val) =>
                                            onPerPageChange(Number(val))
                                        }
                                        className="w-20"
                                    >
                                        <Select.Trigger>
                                            <Select.Value />
                                        </Select.Trigger>
                                        <Select.Popover>
                                            <ListBox>
                                                <ListBox.Item
                                                    id="10"
                                                    textValue="10"
                                                >
                                                    10
                                                </ListBox.Item>
                                                <ListBox.Item
                                                    id="25"
                                                    textValue="25"
                                                >
                                                    25
                                                </ListBox.Item>
                                                <ListBox.Item
                                                    id="50"
                                                    textValue="50"
                                                >
                                                    50
                                                </ListBox.Item>
                                                <ListBox.Item
                                                    id="100"
                                                    textValue="100"
                                                >
                                                    100
                                                </ListBox.Item>
                                            </ListBox>
                                        </Select.Popover>
                                    </Select>
                                </div>
                            )}
                        </div>

                        {onPageChange && (
                            <Pagination size="sm">
                                <Pagination.Content>
                                    <Pagination.Item>
                                        <Pagination.Previous
                                            isDisabled={
                                                pagination.currentPage <= 1
                                            }
                                            onPress={() =>
                                                onPageChange(
                                                    pagination.currentPage - 1,
                                                )
                                            }
                                        >
                                            <Pagination.PreviousIcon />
                                        </Pagination.Previous>
                                    </Pagination.Item>
                                    {Array.from(
                                        { length: pagination.lastPage },
                                        (_, i) => i + 1,
                                    ).map((p) => (
                                        <Pagination.Item key={p}>
                                            <Pagination.Link
                                                isActive={
                                                    p === pagination.currentPage
                                                }
                                                onPress={() => onPageChange(p)}
                                            >
                                                {p}
                                            </Pagination.Link>
                                        </Pagination.Item>
                                    ))}
                                    <Pagination.Item>
                                        <Pagination.Next
                                            isDisabled={
                                                pagination.currentPage >=
                                                pagination.lastPage
                                            }
                                            onPress={() =>
                                                onPageChange(
                                                    pagination.currentPage + 1,
                                                )
                                            }
                                        >
                                            <Pagination.NextIcon />
                                        </Pagination.Next>
                                    </Pagination.Item>
                                </Pagination.Content>
                            </Pagination>
                        )}
                    </Table.Footer>
                )}
            </Table>
        </div>
    );
}
