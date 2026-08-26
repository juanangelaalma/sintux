import type { ReactNode } from 'react';

type PurchaseDocumentHeaderProps = {
    eyebrow?: string;
    title: string;
    actions?: ReactNode;
};

export default function PurchaseDocumentHeader({
    eyebrow = 'Pembelian',
    title,
    actions,
}: PurchaseDocumentHeaderProps) {
    return (
        <div className="flex flex-col gap-4 border-b border-border pb-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p className="text-xs font-semibold tracking-wider text-accent uppercase">
                    {eyebrow}
                </p>
                <h1 className="mt-1 text-2xl font-bold text-foreground">
                    {title}
                </h1>
            </div>
            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
