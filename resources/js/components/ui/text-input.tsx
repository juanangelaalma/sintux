import { forwardRef, useState } from 'react';
import type { InputHTMLAttributes } from 'react';
import { EyeCloseIcon, EyeIcon } from '@/icons';
import { cn } from '@/lib/utils';

const TextInput = forwardRef<
    HTMLInputElement,
    InputHTMLAttributes<HTMLInputElement>
>(({ className, type, ...props }, ref) => {
    const [showPassword, setShowPassword] = useState(false);
    const isPassword = type === 'password';

    const input = (
        <input
            ref={ref}
            type={isPassword && showPassword ? 'text' : type}
            className={cn(
                'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-gray-900 shadow-sm focus:border-brand-500 focus:ring-brand-500 disabled:bg-gray-100 dark:border-gray-700 dark:bg-gray-900 dark:text-white dark:disabled:bg-gray-800',
                isPassword &&
                    'h-11 appearance-none bg-transparent px-4 py-2.5 pr-11 text-sm text-gray-800 shadow-theme-xs placeholder:text-gray-400 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/10 focus:outline-hidden dark:bg-dark-900 dark:text-white/90 dark:placeholder:text-white/30 dark:focus:border-brand-800',
                className,
            )}
            {...props}
        />
    );

    if (!isPassword) {
        return input;
    }

    return (
        <div className="relative">
            {input}
            <button
                type="button"
                onClick={() => setShowPassword((visible) => !visible)}
                className="absolute top-1/2 right-4 z-10 -translate-y-1/2 cursor-pointer rounded-sm text-gray-500 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 dark:text-gray-400"
                aria-label={showPassword ? 'Hide password' : 'Show password'}
                aria-pressed={showPassword}
            >
                {showPassword ? (
                    <EyeCloseIcon className="size-5 fill-current" />
                ) : (
                    <EyeIcon className="size-5 fill-current" />
                )}
            </button>
        </div>
    );
});

TextInput.displayName = 'TextInput';

export default TextInput;
