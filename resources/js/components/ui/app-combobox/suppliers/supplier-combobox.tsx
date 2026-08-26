'use client';

import type { Key } from 'react';

import {
    AppComboBox,
} from '@/components/ui/app-combobox';

export type Supplier = {
    id: number;
    name: string;
    code?: string;
    email?: string;
    address?: string;
};

type SupplierComboBoxProps = {
    suppliers: Supplier[];

    value?: number | string | null;

    onChange?: (
        supplierId: number | null,
    ) => void;

    onAddSupplier?: () => void;

    isLoading?: boolean;

    isLoadingMore?: boolean;

    onLoadMore?: () => void;

    inputValue?: string;

    onInputChange?: (value: string) => void;

    error?: string;

    isRequired?: boolean;
};

export function SupplierComboBox({
    suppliers,

    value,

    onChange,

    onAddSupplier,

    isLoading = false,

    isLoadingMore = false,

    onLoadMore,

    inputValue,

    onInputChange,

    error,

    isRequired = true,
}: SupplierComboBoxProps) {
    /**
     * HeroUI menggunakan Key.
     *
     * Karena ID supplier dari backend berupa number,
     * kita convert ke string untuk ComboBox.
     */
    const selectedKey: Key | null =
        value !== null &&
        value !== undefined &&
        value !== ''
            ? String(value)
            : null;

    /**
     * Ketika user memilih supplier.
     */
    const handleSelectionChange = (
        key: Key | null,
    ) => {
        if (
            key === null ||
            key === ''
        ) {
            onChange?.(null);
            return;
        }

        const supplierId = Number(key);

        if (Number.isNaN(supplierId)) {
            onChange?.(null);
            return;
        }

        onChange?.(supplierId);
    };

    return (
        <div className="w-full">
            <AppComboBox
                items={suppliers}
                label="Supplier"
                placeholder="Pilih supplier"
                selectedKey={selectedKey}
                onSelectionChange={
                    handleSelectionChange
                }
                inputValue={inputValue}
                onInputChange={onInputChange}
                isRequired={isRequired}
                isLoading={isLoading}
                isLoadingMore={isLoadingMore}
                onLoadMore={onLoadMore}
                getItemLabel={(item) =>
                    item.name
                }

                /**
                 * Footer selalu muncul.
                 */
                footerAction={{
                    label: '+ Tambah kontak',
                    onClick: () => {
                        onAddSupplier?.();
                    },
                }}
            />

            {error && (
                <p
                    className="
                        mt-1
                        text-xs
                        text-danger
                    "
                >
                    {error}
                </p>
            )}
        </div>
    );
}