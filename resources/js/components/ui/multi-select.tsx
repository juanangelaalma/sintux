import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

export type MultiSelectOption = {
    value: number;
    label: string;
};

type MultiSelectProps = {
    options: MultiSelectOption[];
    values: number[];
    onChange: (values: number[]) => void;
    placeholder?: string;
    className?: string;
};

export default function MultiSelect({
    options,
    values,
    onChange,
    placeholder = 'Select...',
    className,
}: MultiSelectProps) {
    const [isOpen, setIsOpen] = useState(false);
    const rootRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (rootRef.current && !rootRef.current.contains(event.target as Node)) {
                setIsOpen(false);
            }
        }

        function handleKeyDown(event: KeyboardEvent) {
            if (event.key === 'Escape') {
                setIsOpen(false);
            }
        }

        document.addEventListener('mousedown', handleClickOutside);
        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('mousedown', handleClickOutside);
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, []);

    const toggle = (value: number) => {
        onChange(
            values.includes(value)
                ? values.filter((v) => v !== value)
                : [...values, value],
        );
    };

    const selectedLabels = options
        .filter((o) => values.includes(o.value))
        .map((o) => o.label);

    return (
        <div ref={rootRef} className={cn('relative', className)}>
            <button
                type="button"
                onClick={() => setIsOpen((open) => !open)}
                aria-haspopup="listbox"
                aria-expanded={isOpen}
                className="mt-1 flex w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
            >
                <span
                    className={cn(
                        'truncate',
                        selectedLabels.length === 0 &&
                            'text-gray-400 dark:text-gray-500',
                    )}
                >
                    {selectedLabels.length > 0
                        ? selectedLabels.join(', ')
                        : placeholder}
                </span>
                <svg
                    className={cn(
                        'shrink-0 stroke-gray-500 transition-transform duration-200 dark:stroke-gray-400',
                        isOpen && 'rotate-180',
                    )}
                    width="18"
                    height="20"
                    viewBox="0 0 18 20"
                    fill="none"
                    aria-hidden="true"
                    xmlns="http://www.w3.org/2000/svg"
                >
                    <path
                        d="M4.3125 8.65625L9 13.3437L13.6875 8.65625"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    />
                </svg>
            </button>

            {isOpen && (
                <div className="absolute z-40 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark">
                    {options.length === 0 ? (
                        <p className="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                            No options available
                        </p>
                    ) : (
                        <ul className="max-h-48 overflow-y-auto p-1">
                            {options.map((option) => {
                                const selected = values.includes(option.value);

                                return (
                                    <li key={option.value}>
                                        <label
                                            className="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-white/5"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selected}
                                                onChange={() => toggle(option.value)}
                                                className="rounded border-gray-300"
                                            />
                                            <span className="truncate">
                                                {option.label}
                                            </span>
                                        </label>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>
            )}
        </div>
    );
}
