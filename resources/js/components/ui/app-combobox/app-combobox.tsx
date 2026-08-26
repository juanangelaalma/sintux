'use client';

import {
    ComboBox,
    Input,
    Label,
    ListBox,
    Spinner,
} from '@heroui/react';

import type { Key } from '@react-types/shared';
import { useMemo, useState } from 'react';

import type {
    AppComboBoxItem,
    AppComboBoxProps,
} from './types';

export function AppComboBox<T extends AppComboBoxItem>({
    items,

    label,
    placeholder = 'Pilih...',

    selectedKey,
    onSelectionChange,

    inputValue: controlledInputValue,
    onInputChange: controlledOnInputChange,

    footerAction,

    creatable = false,
    onCreate,

    isRequired = false,
    isDisabled = false,
    isLoading = false,
    isLoadingMore = false,
    onLoadMore,

    emptyMessage = 'Tidak ada data.',

    className,

    renderItem,

    getItemLabel = (item) =>
        String(item.name ?? item.id),
}: AppComboBoxProps<T>) {
    /**
     * ---------------------------------------------------------
     * INPUT VALUE
     * ---------------------------------------------------------
     *
     * Component bisa digunakan:
     *
     * 1. Controlled
     *    inputValue + onInputChange
     *
     * 2. Uncontrolled
     *    component mengelola state sendiri.
     */
    const [internalInputValue, setInternalInputValue] =
        useState('');

    const inputValue =
        controlledInputValue ?? internalInputValue;

    const handleInputChange = (value: string) => {
        if (controlledOnInputChange) {
            controlledOnInputChange(value);
            return;
        }

        setInternalInputValue(value);
    };

    /**
     * ---------------------------------------------------------
     * CREATEABLE
     * ---------------------------------------------------------
     *
     * Tentukan apakah value yang diketik sudah ada.
     */
    const normalizedInput =
        inputValue.trim().toLowerCase();

    const exactMatchExists = useMemo(() => {
        if (!normalizedInput) {
            return false;
        }

        return items.some((item) => {
            return (
                getItemLabel(item)
                    .trim()
                    .toLowerCase() === normalizedInput
            );
        });
    }, [
        items,
        normalizedInput,
        getItemLabel,
    ]);

    const canCreate =
        creatable &&
        normalizedInput.length > 0 &&
        !exactMatchExists;

    /**
     * ---------------------------------------------------------
     * SELECTION
     * ---------------------------------------------------------
     */
    const handleSelectionChange = (
        key: Key | null,
    ) => {
        onSelectionChange?.(key);
    };

    /**
     * ---------------------------------------------------------
     * CREATE
     * ---------------------------------------------------------
     */
    const handleCreate = () => {
        const value = inputValue.trim();

        if (!value) {
            return;
        }

        onCreate?.(value);
    };

    return (
        <ComboBox
            className={className ?? 'w-full'}
            selectedKey={selectedKey}
            onSelectionChange={handleSelectionChange}
            inputValue={inputValue}
            onInputChange={handleInputChange}
            isRequired={isRequired}
            isDisabled={isDisabled}
            allowsEmptyCollection
            menuTrigger="focus"

            /**
             * HeroUI akan melakukan filtering
             * berdasarkan textValue dari ListBox.Item.
             */
            defaultFilter={(text, input) => {
                if (!input) {
                    return true;
                }

                return text
                    .toLowerCase()
                    .includes(input.toLowerCase());
            }}
        >
            {/* =================================================
                LABEL
            ================================================= */}

            {label && (
                <Label
                    className="
                        mb-1
                        block
                        text-xs
                        font-semibold
                        text-foreground
                    "
                >
                    {label}
                </Label>
            )}

            {/* =================================================
                INPUT
            ================================================= */}

            <ComboBox.InputGroup
                className="
                    flex
                    h-9
                    w-full
                    items-center
                    rounded-lg
                    border
                    border-border
                    bg-surface
                    px-2.5
                    focus-within:border-accent
                    focus-within:ring-1
                    focus-within:ring-accent/20
                "
            >
                <Input
                    placeholder={placeholder}
                    className="
                        min-w-0
                        flex-1
                        border-none
                        bg-transparent
                        p-0
                        text-sm
                        text-foreground
                        outline-none
                        shadow-none
                        focus:border-none
                        focus:outline-none
                        focus:ring-0
                    "
                />

                <ComboBox.Trigger
                    className="
                        shrink-0
                        text-muted
                        hover:text-foreground
                    "
                />
            </ComboBox.InputGroup>

            {/* =================================================
                POPOVER
            ================================================= */}

            <ComboBox.Popover
                placement="bottom"
                className="
                    w-[var(--trigger-width)]
                    overflow-hidden
                    rounded-lg
                    border
                    border-border
                    bg-surface
                    p-0
                    shadow-lg
                    z-50
                "
            >
                {/* =================================================
                    OPTIONS
                ================================================= */}

                <ListBox
                    aria-label={
                        label ?? 'Options'
                    }
                    className="
                        max-h-64
                        overflow-y-auto
                        p-1
                    "
                    onScroll={(e) => {
                        if (!onLoadMore || isLoading || isLoadingMore) return;
                        const target = e.currentTarget;
                        if (target.scrollTop + target.clientHeight >= target.scrollHeight - 20) {
                            onLoadMore();
                        }
                    }}
                >
                    {isLoading ? (
                        <div
                            className="
                                flex
                                items-center
                                justify-center
                                py-6
                            "
                        >
                            <Spinner size="sm" />
                        </div>
                    ) : items.length === 0 ? (
                        <div className="px-3 py-6 text-center text-sm text-muted">
                            {emptyMessage}
                        </div>
                    ) : (
                        <>
                            {items.map((item) => (
                                <ListBox.Item
                                    key={String(item.id)}
                                    id={String(item.id)}
                                    textValue={getItemLabel(item)}
                                    className="
                                        flex
                                        min-h-9
                                        cursor-pointer
                                        items-center
                                        justify-between
                                        rounded-md
                                        px-2.5
                                        py-2
                                        text-sm
                                        text-foreground
                                        outline-none
                                        hover:bg-surface-secondary/70
                                        focus:bg-surface-secondary/70
                                        data-[selected=true]:bg-surface-secondary
                                    "
                                >
                                    {renderItem ? (
                                        renderItem(item)
                                    ) : (
                                        <span>
                                            {getItemLabel(item)}
                                        </span>
                                    )}

                                    <ListBox.ItemIndicator />
                                </ListBox.Item>
                            ))}
                            {isLoadingMore && (
                                <div className="flex items-center justify-center py-2">
                                    <Spinner size="sm" />
                                </div>
                            )}
                        </>
                    )}
                </ListBox>

                {/* =================================================
                    CREATE NEW ITEM
                ================================================= */}

                {canCreate && (
                    <div
                        className="
                            border-t
                            border-border
                            p-1
                        "
                    >
                        <button
                            type="button"
                            onClick={handleCreate}
                            className="
                                flex
                                h-9
                                w-full
                                items-center
                                justify-center
                                rounded-md
                                px-3
                                text-sm
                                text-accent
                                transition-colors
                                hover:bg-surface-secondary/70
                            "
                        >
                            Add "{inputValue.trim()}"
                        </button>
                    </div>
                )}

                {/* =================================================
                    STATIC FOOTER ACTION
                ================================================= */}

                {footerAction && (
                    <div
                        className="
                            border-t
                            border-border
                            p-1
                        "
                    >
                        <button
                            type="button"
                            onClick={footerAction.onClick}
                            className="
                                flex
                                h-9
                                w-full
                                items-center
                                justify-center
                                rounded-md
                                px-3
                                text-sm
                                text-accent
                                transition-colors
                                hover:bg-surface-secondary/70
                            "
                        >
                            {footerAction.label}
                        </button>
                    </div>
                )}
            </ComboBox.Popover>
        </ComboBox>
    );
}