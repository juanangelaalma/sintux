import { useEffect } from 'react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type ModalProps = {
    title: ReactNode;
    description?: ReactNode;
    children: ReactNode;
    maxWidth?: 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl';
    onClose?: () => void;
    className?: string;
};

const widths = {
    md: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    '2xl': 'max-w-2xl',
    '3xl': 'max-w-3xl',
    '4xl': 'max-w-4xl',
};

export default function Modal({
    title,
    description,
    children,
    maxWidth = 'md',
    onClose,
    className,
}: ModalProps) {
    useEffect(() => {
        if (!onClose) {
            return;
        }

        const handleKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', handleKeyDown);

        return () => {
            document.removeEventListener('keydown', handleKeyDown);
        };
    }, [onClose]);

    return (
        <div className="fixed inset-0 z-[100000] flex items-center justify-center bg-black/50 p-4">
            <div
                className={cn(
                    'max-h-[calc(100vh-2rem)] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900',
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
