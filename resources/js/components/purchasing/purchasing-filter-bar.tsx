import { Button, Label, ListBox, SearchField, Select } from '@heroui/react';
import { Icon } from '@iconify/react';
import { router } from '@inertiajs/react';
import { Filter } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

export type PurchasingFilter = {
    search?: string;
    status?: string;
};

export type StatusOption = {
    value: string;
    label: string;
};

type PurchasingFilterBarProps = {
    initialFilters: PurchasingFilter;
    statusOptions?: StatusOption[];
    searchPlaceholder?: string;
};

const DEFAULT_STATUS_OPTIONS: StatusOption[] = [
    { value: 'unpaid', label: 'Belum Lunas' },
    { value: 'pending', label: 'Belum Dibayar' },
    { value: 'partial', label: 'Dibayar Sebagian' },
    { value: 'paid', label: 'Lunas' },
    { value: 'overdue', label: 'Lewat Jatuh Tempo' },
    { value: 'completed', label: 'Selesai' },
];

const ALL_STATUS = 'all';

function mergeParams(
    filters: PurchasingFilter,
    page?: number,
): Record<string, string> {
    const params: Record<string, string> = {};

    if (filters.search) {
        params.search = filters.search;
    }

    if (filters.status) {
        params.status = filters.status;
    }

    if (page) {
        params.page = String(page);
    }

    return params;
}

export default function PurchasingFilterBar({
    initialFilters,
    statusOptions = DEFAULT_STATUS_OPTIONS,
    searchPlaceholder = 'Cari transaksi',
}: PurchasingFilterBarProps) {
    const [search, setSearch] = useState(initialFilters.search ?? '');
    const [status, setStatus] = useState(initialFilters.status ?? '');
    const statusRef = useRef(status);
    const timer = useRef<ReturnType<typeof setTimeout> | undefined>(undefined);

    useEffect(() => {
        statusRef.current = status;
    }, [status]);

    useEffect(() => () => clearTimeout(timer.current), []);

    const apply = (nextSearch: string, nextStatus: string, page?: number) => {
        router.get(
            window.location.pathname,
            mergeParams({ search: nextSearch, status: nextStatus }, page),
            {
                preserveState: true,
                replace: true,
                preserveScroll: true,
            },
        );
    };

    const handleSearch = (value: string) => {
        setSearch(value);
        clearTimeout(timer.current);
        timer.current = setTimeout(() => apply(value, statusRef.current), 300);
    };

    const handleStatus = (value: string) => {
        setStatus(value);
        apply(search, value);
    };

    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {/* Left: Status Filter Select */}
            <div className="w-full sm:w-56">
                <Select
                    fullWidth
                    placeholder="Semua Status"
                    value={status || ALL_STATUS}
                    onChange={(value) =>
                        handleStatus(value === ALL_STATUS ? '' : String(value))
                    }
                >
                    <Label className="sr-only">Filter status</Label>
                    <Select.Trigger className="bg-surface">
                        <Select.Value />
                        <Select.Indicator />
                    </Select.Trigger>
                    <Select.Popover>
                        <ListBox>
                            <ListBox.Item
                                id={ALL_STATUS}
                                textValue="Semua Status"
                            >
                                Semua Status
                                <ListBox.ItemIndicator />
                            </ListBox.Item>
                            {statusOptions.map((option) => (
                                <ListBox.Item
                                    key={option.value}
                                    id={option.value}
                                    textValue={option.label}
                                >
                                    {option.label}
                                    <ListBox.ItemIndicator />
                                </ListBox.Item>
                            ))}
                        </ListBox>
                    </Select.Popover>
                </Select>
            </div>

            {/* Right: Export Toolbar & Search Input */}
            <div className="flex flex-wrap items-center gap-3">
                {/* Export Tools Toolbar Placeholder */}
                <div className="hidden items-center gap-1 rounded-lg border border-border/80 bg-surface p-1 shadow-2xs md:flex">
                    <Button
                        isIconOnly
                        size="sm"
                        variant="ghost"
                        aria-label="Salin data"
                    >
                        <Icon
                            className="size-4 text-muted"
                            icon="gravity-ui:copy"
                        />
                    </Button>
                    <Button
                        isIconOnly
                        size="sm"
                        variant="ghost"
                        aria-label="Export CSV"
                    >
                        <Icon
                            className="size-4 text-muted"
                            icon="gravity-ui:file-csv"
                        />
                    </Button>
                    <Button
                        isIconOnly
                        size="sm"
                        variant="ghost"
                        aria-label="Export Excel"
                    >
                        <Icon
                            className="size-4 text-muted"
                            icon="gravity-ui:file-text"
                        />
                    </Button>
                    <Button
                        isIconOnly
                        size="sm"
                        variant="ghost"
                        aria-label="Export PDF"
                    >
                        <Icon
                            className="size-4 text-muted"
                            icon="gravity-ui:file-pdf"
                        />
                    </Button>
                    <Button
                        isIconOnly
                        size="sm"
                        variant="ghost"
                        aria-label="Cetak tabel"
                    >
                        <Icon
                            className="size-4 text-muted"
                            icon="gravity-ui:printer"
                        />
                    </Button>
                </div>

                {/* Search Input Field */}
                <div className="w-full sm:w-64">
                    <SearchField
                        fullWidth
                        value={search}
                        onChange={handleSearch}
                        aria-label="Cari transaksi"
                    >
                        <SearchField.Group>
                            <SearchField.SearchIcon />
                            <SearchField.Input
                                placeholder={searchPlaceholder}
                            />
                            <SearchField.ClearButton />
                        </SearchField.Group>
                    </SearchField>
                </div>

                {/* Filter Trigger Button */}
                <Button variant="secondary" className="gap-1.5 font-medium">
                    <Filter className="size-4 text-muted" />
                    Filter
                </Button>
            </div>
        </div>
    );
}
