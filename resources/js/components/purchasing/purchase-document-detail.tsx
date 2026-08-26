import type { ReactNode } from 'react';
import PurchasingStatusBadge from '@/components/purchasing/purchasing-status-badge';

export type DetailRow = {
    label: string;
    value: string;
};

type PurchaseDocumentDetailProps = {
    status: string;
    statusLabel: string;
    rows: DetailRow[];
    note?: string | null;
    children?: ReactNode;
};

export default function PurchaseDocumentDetail({
    status,
    statusLabel,
    rows,
    note,
    children,
}: PurchaseDocumentDetailProps) {
    return (
        <div className="space-y-6 rounded-xl border border-border bg-surface p-6 shadow-xs sm:p-8">
            <div className="grid grid-cols-1 gap-4 border-b border-border/60 pb-6 sm:grid-cols-2">
                <div>
                    <p className="text-xs font-semibold tracking-wider text-muted uppercase">
                        {statusLabel}
                    </p>
                    <div className="mt-2">
                        <PurchasingStatusBadge status={status} />
                    </div>
                </div>
                <dl className="space-y-2 text-sm">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="flex items-center justify-between gap-4 sm:justify-end"
                        >
                            <dt className="text-muted">{row.label}</dt>
                            <dd className="font-semibold text-foreground">
                                {row.value}
                            </dd>
                        </div>
                    ))}
                </dl>
            </div>

            {note && (
                <div className="rounded-lg border border-border bg-surface-secondary p-4 text-sm">
                    <p className="text-xs font-semibold tracking-wider text-muted uppercase">
                        Catatan
                    </p>
                    <p className="mt-1 whitespace-pre-wrap text-foreground">
                        {note}
                    </p>
                </div>
            )}

            {children}
        </div>
    );
}
