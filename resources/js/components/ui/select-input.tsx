import type { SelectHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type SelectInputProps = SelectHTMLAttributes<HTMLSelectElement>;

export default function SelectInput({ className, ...props }: SelectInputProps) {
    return (
        <select
            className={cn(
                'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white',
                className,
            )}
            {...props}
        />
    );
}
