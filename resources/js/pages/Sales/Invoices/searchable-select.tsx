import type { Key } from '@heroui/react';
import {
    Autocomplete,
    EmptyState,
    Label,
    ListBox,
    SearchField,
    useFilter,
} from '@heroui/react';

export type SearchableOption = {
    id: number;
    name: string;
    hint?: string;
    /** Teks tambahan untuk pencarian, tidak ditampilkan. */
    keywords?: string;
};

type SearchableSelectProps = {
    label?: string;
    placeholder?: string;
    items: SearchableOption[];
    value: number | string | '';
    onChange: (id: number) => void;
    error?: string;
    isRequired?: boolean;
    searchPlaceholder?: string;
    emptyMessage?: string;
};

/**
 * Dropdown dengan pencarian untuk opsi dalam jumlah besar
 * (pelanggan, karyawan, produk). Single-select, full width.
 */
export default function SearchableSelect({
    label,
    placeholder = 'Ketik untuk mencari...',
    items,
    value,
    onChange,
    error,
    isRequired = false,
    searchPlaceholder = 'Cari...',
    emptyMessage = 'Tidak ditemukan',
}: SearchableSelectProps) {
    const { contains } = useFilter({ sensitivity: 'base' });

    return (
        <div>
            <Autocomplete
                fullWidth
                isRequired={isRequired}
                isInvalid={Boolean(error)}
                placeholder={placeholder}
                selectionMode="single"
                value={value ? String(value) : null}
                onChange={(key: Key | Key[] | null) => {
                    if (key === null || Array.isArray(key)) {
                        return;
                    }

                    onChange(Number(key));
                }}
            >
                <Label
                    className={
                        label
                            ? 'mb-1 block text-xs font-semibold text-foreground'
                            : 'sr-only'
                    }
                >
                    {label ?? placeholder}{' '}
                    {isRequired && label && (
                        <span className="text-danger">*</span>
                    )}
                </Label>
                <Autocomplete.Trigger>
                    <Autocomplete.Value />
                    <Autocomplete.ClearButton />
                    <Autocomplete.Indicator />
                </Autocomplete.Trigger>
                <Autocomplete.Popover>
                    <Autocomplete.Filter filter={contains}>
                        <SearchField
                            autoFocus
                            aria-label={`Cari ${label}`}
                            variant="secondary"
                        >
                            <SearchField.Group>
                                <SearchField.SearchIcon />
                                <SearchField.Input
                                    placeholder={searchPlaceholder}
                                />
                                <SearchField.ClearButton />
                            </SearchField.Group>
                        </SearchField>
                        <ListBox
                            className="max-h-[320px] overflow-y-auto"
                            renderEmptyState={() => (
                                <EmptyState>{emptyMessage}</EmptyState>
                            )}
                        >
                            {items.map((item) => (
                                <ListBox.Item
                                    key={item.id}
                                    id={String(item.id)}
                                    textValue={[
                                        item.name,
                                        item.hint,
                                        item.keywords,
                                    ]
                                        .filter(Boolean)
                                        .join(' ')}
                                >
                                    <span className="block truncate">
                                        {item.name}
                                    </span>
                                    {item.hint && (
                                        <span className="block truncate font-mono text-[11px] text-muted">
                                            {item.hint}
                                        </span>
                                    )}
                                    <ListBox.ItemIndicator />
                                </ListBox.Item>
                            ))}
                        </ListBox>
                    </Autocomplete.Filter>
                </Autocomplete.Popover>
            </Autocomplete>
            {error && <p className="mt-1 text-xs text-danger">{error}</p>}
        </div>
    );
}
