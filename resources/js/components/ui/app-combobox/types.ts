import type { Key } from '@react-types/shared';
import type { ReactNode } from 'react';

export type AppComboBoxItem = {
    id: number | string;
    name?: string;
    [key: string]: unknown;
};

export type AppComboBoxFooterAction = {
    label: string;
    onClick: () => void;
};

export type AppComboBoxProps<T extends AppComboBoxItem> = {
    /**
     * Data yang ditampilkan pada ComboBox.
     */
    items: T[];

    /**
     * Label field.
     */
    label?: string;

    /**
     * Placeholder input.
     */
    placeholder?: string;

    /**
     * Selected item key.
     */
    selectedKey?: Key | null;

    /**
     * Callback ketika item dipilih.
     */
    onSelectionChange?: (key: Key | null) => void;

    /**
     * Controlled input value.
     */
    inputValue?: string;

    /**
     * Callback ketika user mengetik.
     */
    onInputChange?: (value: string) => void;

    /**
     * Action di bagian bawah dropdown.
     *
     * Contoh:
     * + Tambah kontak
     * + Tambah term
     */
    footerAction?: AppComboBoxFooterAction;

    /**
     * Mengizinkan user membuat item baru.
     *
     * Digunakan misalnya untuk Tag.
     */
    creatable?: boolean;

    /**
     * Dipanggil ketika user membuat item baru.
     */
    onCreate?: (value: string) => void;

    /**
     * Required state.
     */
    isRequired?: boolean;

    /**
     * Disabled state.
     */
    isDisabled?: boolean;

    /**
     * Loading state saat initial fetch.
     */
    isLoading?: boolean;

    /**
     * Loading state saat pagination / infinite scroll.
     */
    isLoadingMore?: boolean;

    /**
     * Callback ketika user melakukan scroll hingga batas bawah (infinite scroll).
     */
    onLoadMore?: () => void;

    /**
     * Pesan ketika tidak ada data.
     */
    emptyMessage?: string;

    /**
     * Class tambahan untuk root ComboBox.
     */
    className?: string;

    /**
     * Custom render item.
     */
    renderItem?: (item: T) => ReactNode;

    /**
     * Cara mengambil label dari item.
     */
    getItemLabel?: (item: T) => string;
};