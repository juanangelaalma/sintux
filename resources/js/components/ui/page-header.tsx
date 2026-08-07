import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: ReactNode;
    description?: ReactNode;
    actions?: ReactNode;
};

export default function PageHeader({
    title,
    description,
    actions,
}: PageHeaderProps) {
    return (
        <div className="flex items-center justify-between">
            <div>
                <h1 className="text-2xl font-semibold text-gray-900 dark:text-white">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {description}
                    </p>
                )}
            </div>
            {actions}
        </div>
    );
}
