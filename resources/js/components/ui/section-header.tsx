import type { ReactNode } from 'react';

type SectionHeaderProps = {
    title: ReactNode;
    description?: ReactNode;
};

export default function SectionHeader({
    title,
    description,
}: SectionHeaderProps) {
    return (
        <header>
            <h2 className="mb-0.5 text-base font-medium">{title}</h2>
            {description && (
                <p className="text-sm text-gray-500 dark:text-gray-400">
                    {description}
                </p>
            )}
        </header>
    );
}
