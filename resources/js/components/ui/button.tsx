import type { ButtonHTMLAttributes } from 'react';
import { cn } from '@/lib/utils';

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> & {
    variant?: 'primary' | 'secondary' | 'danger';
    fullWidth?: boolean;
};

const variants = {
    primary: 'bg-brand-500 text-white hover:bg-brand-600',
    secondary:
        'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800',
    danger: 'bg-red-600 text-white hover:bg-red-700',
};

export default function Button({
    className,
    variant = 'primary',
    fullWidth = false,
    type = 'button',
    ...props
}: ButtonProps) {
    return (
        <button
            type={type}
            className={cn(
                'inline-flex items-center justify-center rounded-lg px-4 py-2.5 text-sm font-medium disabled:opacity-50',
                variants[variant],
                fullWidth && 'w-full',
                className,
            )}
            {...props}
        />
    );
}
