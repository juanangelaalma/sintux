'use client';

import { Tag as HeroTag, TagGroup } from '@heroui/react';
import { XCircle } from 'lucide-react';
import {
    ListBox as RACListBox,
    ListBoxItem,
    Popover as RACPopover,
} from 'react-aria-components';
import type { Key, KeyboardEvent, MouseEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';

export type TagItem = {
    id: number | string;
    name: string;
    [key: string]: unknown;
};

type TagComboBoxProps = {
    tags: TagItem[];
    selectedTagIds?: (string | number)[];
    onSelectedTagsChange?: (tagIds: (string | number)[]) => void;
    onCreateTag?: (name: string) => void;
    isLoading?: boolean;
    error?: string;
    isRequired?: boolean;
    placeholder?: string;
    label?: string;
};

export function TagComboBox({
    tags,
    selectedTagIds = [],
    onSelectedTagsChange,
    onCreateTag,
    error,
    isRequired = false,
    placeholder = 'Pilih tag',
    label = 'Tag',
}: TagComboBoxProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [inputValue, setInputValue] = useState('');
    const inputRef = useRef<HTMLInputElement>(null);
    const triggerRef = useRef<HTMLDivElement>(null);

    // Lebar trigger diukur manual, lalu dipasang sebagai inline style
    // di panel popover — RAC tidak meng-inject --trigger-width untuk
    // popover yang dipakai di luar compound ComboBox.
    const [triggerWidth, setTriggerWidth] = useState<number>();

    useEffect(() => {
        const el = triggerRef.current;
        if (!el) return;

        const updateWidth = () => setTriggerWidth(el.offsetWidth);
        updateWidth();

        const observer = new ResizeObserver(updateWidth);
        observer.observe(el);
        return () => observer.disconnect();
    }, []);

    const normalizedInput = inputValue.trim().toLowerCase();

    // Tags yang belum dipilih
    const availableTags = useMemo(() => {
        return tags.filter(
            (t) => !selectedTagIds.some((id) => String(id) === String(t.id)),
        );
    }, [tags, selectedTagIds]);

    const filteredTags = useMemo(() => {
        if (!normalizedInput) return availableTags;
        return availableTags.filter((t) =>
            t.name.toLowerCase().includes(normalizedInput),
        );
    }, [availableTags, normalizedInput]);

    const exactMatchExists = useMemo(() => {
        if (!normalizedInput) return false;
        return tags.some((t) => t.name.toLowerCase() === normalizedInput);
    }, [tags, normalizedInput]);

    const canCreate =
        normalizedInput.length > 0 && !exactMatchExists && !!onCreateTag;

    const selectedTags = useMemo(() => {
        return tags.filter((t) =>
            selectedTagIds.some((id) => String(id) === String(t.id)),
        );
    }, [tags, selectedTagIds]);

    const handleSelectTag = (tag: TagItem) => {
        onSelectedTagsChange?.([...selectedTagIds, tag.id]);
        setInputValue('');
        setTimeout(() => {
            inputRef.current?.focus();
        }, 10);
    };

    const handleRemoveTag = (keys: Set<Key>) => {
        const remaining = selectedTagIds.filter(
            (id) => !keys.has(String(id)) && !keys.has(id),
        );
        onSelectedTagsChange?.(remaining);
        setTimeout(() => {
            inputRef.current?.focus();
        }, 10);
    };

    const handleClearAll = (e: MouseEvent) => {
        e.stopPropagation();
        onSelectedTagsChange?.([]);
        setInputValue('');
        setTimeout(() => {
            inputRef.current?.focus();
        }, 10);
    };

    const handleCreate = () => {
        const value = inputValue.trim();
        if (!value || !onCreateTag) return;
        onCreateTag(value);
        setInputValue('');
        setTimeout(() => {
            inputRef.current?.focus();
        }, 10);
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (
            e.key === 'Backspace' &&
            !inputValue &&
            selectedTagIds.length > 0
        ) {
            const next = [...selectedTagIds];
            next.pop();
            onSelectedTagsChange?.(next);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (filteredTags.length > 0) {
                handleSelectTag(filteredTags[0]);
            } else if (canCreate) {
                handleCreate();
            }
        } else if (e.key === 'Escape') {
            setIsOpen(false);
        }
    };

    const count = selectedTagIds.length;
    const showEmptyState = filteredTags.length === 0 && !canCreate;

    return (
        <div className="w-full space-y-1">
            <label className="block text-xs font-semibold text-foreground">
                {label}
                {count > 0 && (
                    <span className="ml-1 font-normal text-muted">
                        ({count})
                    </span>
                )}
                {isRequired && <span className="ml-0.5 text-danger">*</span>}
            </label>

            {/* Trigger: chips + input pencarian */}
            <div
                ref={triggerRef}
                onClick={() => {
                    inputRef.current?.focus();
                    setIsOpen(true);
                }}
                className={`relative flex min-h-[42px] w-full cursor-text flex-wrap items-center gap-1.5 rounded-lg border bg-surface p-2 pr-8 transition-colors ${
                    isOpen
                        ? 'border-accent ring-1 ring-accent/30'
                        : 'border-border hover:border-border/80'
                }`}
            >
                {selectedTags.length > 0 && (
                    <TagGroup
                        aria-label="Tags terpilih"
                        size="sm"
                        onRemove={handleRemoveTag}
                    >
                        <TagGroup.List className="flex flex-wrap gap-1.5">
                            {selectedTags.map((tag) => (
                                <HeroTag
                                    key={String(tag.id)}
                                    id={String(tag.id)}
                                    textValue={tag.name}
                                    className="inline-flex items-center gap-1 rounded border border-border/40 bg-surface-secondary/90 px-2 py-0.5 font-normal text-xs text-foreground"
                                >
                                    {tag.name}
                                </HeroTag>
                            ))}
                        </TagGroup.List>
                    </TagGroup>
                )}

                <div className="min-w-[70px] flex-1">
                    <input
                        ref={inputRef}
                        type="text"
                        placeholder={
                            selectedTags.length === 0 ? placeholder : ''
                        }
                        value={inputValue}
                        onChange={(e) => {
                            setInputValue(e.target.value);
                            if (!isOpen) setIsOpen(true);
                        }}
                        onFocus={() => setIsOpen(true)}
                        onKeyDown={handleKeyDown}
                        className="w-full border-none bg-transparent p-0 text-xs text-foreground outline-none focus:ring-0 placeholder:text-muted"
                    />
                </div>

                {count > 0 && (
                    <button
                        type="button"
                        onClick={handleClearAll}
                        className="absolute right-2 top-2.5 rounded p-0.5 text-muted transition-colors hover:text-foreground"
                        title="Hapus semua tag"
                    >
                        <XCircle className="size-4" />
                    </button>
                )}
            </div>

            {/*
                Popover primitif dari react-aria-components:
                satu-satunya cara me-anchor popup ke elemen trigger kustom
                (HeroUI Popover root tidak mendukung triggerRef).
                isNonModal wajib agar fokus TIDAK dipindahkan ke panel —
                mengetik di input harus langsung memfilter.
            */}
            <RACPopover
                isOpen={isOpen}
                onOpenChange={setIsOpen}
                triggerRef={triggerRef}
                placement="bottom start"
                offset={4}
                isNonModal
                className="z-50"
            >
                <div
                    style={{ width: triggerWidth }}
                    className="max-h-64 overflow-hidden rounded-lg border border-border bg-surface shadow-lg"
                >
                    {showEmptyState ? (
                        <div className="px-3 py-4 text-center text-xs text-muted">
                            Tidak ada tag tersedia
                        </div>
                    ) : (
                        <RACListBox
                            aria-label="Pilihan Tag"
                            selectionMode="none"
                            className="max-h-60 overflow-y-auto p-1 text-xs outline-none"
                        >
                            {filteredTags.map((tag) => (
                                <ListBoxItem
                                    key={String(tag.id)}
                                    id={String(tag.id)}
                                    textValue={tag.name}
                                    onAction={() => handleSelectTag(tag)}
                                    className="cursor-pointer rounded px-2.5 py-2 text-xs text-foreground outline-none transition-colors hover:bg-surface-secondary data-[focused]:bg-surface-secondary"
                                >
                                    {tag.name}
                                </ListBoxItem>
                            ))}
                        </RACListBox>
                    )}

                    {canCreate && (
                        <div className="border-t border-border bg-surface-secondary/20 p-1">
                            <button
                                type="button"
                                onClick={handleCreate}
                                className="flex h-8 w-full items-center justify-center rounded px-2 text-xs font-medium text-accent transition-colors hover:bg-surface-secondary"
                            >
                                + Buat tag &quot;{inputValue.trim()}&quot;
                            </button>
                        </div>
                    )}
                </div>
            </RACPopover>

            {error && <p className="mt-1 text-xs text-danger">{error}</p>}
        </div>
    );
}
