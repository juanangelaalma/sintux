import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ModalProps = {
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    maxWidth?: 'md' | 'lg' | '2xl';
    onClose?: () => void;
    className?: string;
};

const widths = { md: 'max-w-md', lg: 'max-w-lg', '2xl': 'max-w-2xl' };

export default function Modal({
    title,
    description,
    children,
    maxWidth = 'md',
    onClose,
    className,
}: ModalProps) {
    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
            <div
                className={cn(
                    'w-full rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900',
                    widths[maxWidth],
                    className,
                )}
            >
                <div className="flex items-center justify-between">
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
                        {title}
                    </h3>
                    {onClose && (
                        <button
                            type="button"
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                        >
                            Close
                        </button>
                    )}
                </div>
                {description && (
                    <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        {description}
                    </p>
                )}
                {children}
            </div>
        </div>
    );
}
