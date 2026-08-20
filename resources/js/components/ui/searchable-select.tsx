import { useEffect, useId, useRef, useState } from 'react';

export type SearchableSelectOption = {
    id: number;
    label: string;
};

type SearchableSelectProps = {
    options: SearchableSelectOption[];
    value: number | null;
    onChange: (value: number | null) => void;
    placeholder: string;
    emptyMessage?: string;
    onCreateOption?: (query: string) => void;
    canCreateOption?: (query: string) => boolean;
    isCreating?: boolean;
};

export function SearchableSelect({
    options,
    value,
    onChange,
    placeholder,
    emptyMessage = 'Tidak ada hasil',
    onCreateOption,
    canCreateOption,
    isCreating = false,
}: SearchableSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [highlightedIndex, setHighlightedIndex] = useState(0);
    const containerRef = useRef<HTMLDivElement>(null);
    const listboxId = useId();
    const selectedOption = options.find((option) => option.id === value);
    const filteredOptions = options.filter((option) => option.label.toLowerCase().includes(query.toLowerCase()));

    useEffect(() => {
        const closeOnOutsideClick = (event: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
                setIsOpen(false);
                setQuery('');
            }
        };

        document.addEventListener('mousedown', closeOnOutsideClick);

        return () => document.removeEventListener('mousedown', closeOnOutsideClick);
    }, []);

    const selectOption = (option: SearchableSelectOption) => {
        onChange(option.id);
        setIsOpen(false);
        setQuery('');
    };

    const open = () => {
        setIsOpen(true);
        setHighlightedIndex(0);
    };

    return (
        <div ref={containerRef} className="relative">
            <div className="flex w-full items-center rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                <input
                    type="text"
                    role="combobox"
                    aria-autocomplete="list"
                    aria-expanded={isOpen}
                    aria-controls={listboxId}
                    value={isOpen ? query : selectedOption?.label ?? ''}
                    onChange={(event) => {
                        setQuery(event.target.value);
                        setHighlightedIndex(0);
                        open();
                    }}
                    onFocus={open}
                    onKeyDown={(event) => {
                        if (event.key === 'ArrowDown') {
                            event.preventDefault();
                            open();
                            setHighlightedIndex((index) => Math.min(index + 1, filteredOptions.length - 1));
                        }

                        if (event.key === 'ArrowUp') {
                            event.preventDefault();
                            setHighlightedIndex((index) => Math.max(index - 1, 0));
                        }

                        if (event.key === 'Enter' && filteredOptions[highlightedIndex]) {
                            event.preventDefault();
                            selectOption(filteredOptions[highlightedIndex]);
                        }

                        if (event.key === 'Escape') {
                            setIsOpen(false);
                            setQuery('');
                        }
                    }}
                    placeholder={placeholder}
                    className="w-full border-none bg-transparent p-0 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                />
                {value !== null && (
                    <button
                        type="button"
                        onClick={() => onChange(null)}
                        className="mr-2 text-slate-400 hover:text-slate-600"
                        aria-label="Hapus pilihan"
                    >
                        &times;
                    </button>
                )}
                <svg
                    className={`size-4 shrink-0 text-slate-400 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="m6 9 6 6 6-6" />
                </svg>
            </div>

            {isOpen && (
                <div
                    id={listboxId}
                    role="listbox"
                    className="absolute z-50 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white text-sm shadow-lg"
                >
                    {filteredOptions.map((option, index) => (
                        <button
                            key={option.id}
                            type="button"
                            role="option"
                            aria-selected={option.id === value}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => selectOption(option)}
                            className={`block w-full px-3 py-2 text-left transition-colors hover:bg-indigo-50 hover:text-indigo-600 ${
                                index === highlightedIndex ? 'bg-indigo-50 text-indigo-600' : 'text-slate-700'
                            } ${option.id === value ? 'font-semibold' : ''}`}
                        >
                            {option.label}
                        </button>
                    ))}

                    {filteredOptions.length === 0 && (
                        <div className="px-3 py-2 text-slate-400">{emptyMessage}</div>
                    )}

                    {onCreateOption && query.trim() && (!canCreateOption || canCreateOption(query.trim())) && (
                        <button
                            type="button"
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => onCreateOption(query.trim())}
                            disabled={isCreating}
                            className="block w-full border-t border-slate-100 px-3 py-2 text-left font-semibold text-indigo-600 hover:bg-indigo-50 disabled:opacity-50"
                        >
                            {isCreating ? 'Menambahkan...' : `Tambahkan "${query.trim()}"`}
                        </button>
                    )}
                </div>
            )}
        </div>
    );
}
