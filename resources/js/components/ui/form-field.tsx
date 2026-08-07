import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { cn } from '@/lib/utils';

type FormFieldProps = {
    label: ReactNode;
    labelAction?: ReactNode;
    htmlFor?: string;
    error?: string;
    children: ReactNode;
    className?: string;
    labelClassName?: string;
};

export default function FormField({
    label,
    labelAction,
    htmlFor,
    error,
    children,
    className,
    labelClassName,
}: FormFieldProps) {
    return (
        <div className={className}>
            <div
                className={cn(
                    labelAction && 'flex items-center justify-between',
                )}
            >
                <label
                    htmlFor={htmlFor}
                    className={cn(
                        'block text-sm font-medium text-gray-700 dark:text-gray-300',
                        labelClassName,
                    )}
                >
                    {label}
                </label>
                {labelAction}
            </div>
            {children}
            <InputError message={error} />
        </div>
    );
}
