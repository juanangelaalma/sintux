import { Badge } from '@heroui/react';
import type { ExpenseStatusValue } from './types';

const STATUS_STYLE: Record<
    ExpenseStatusValue,
    { label: string; className: string }
> = {
    open: {
        label: 'Open',
        className:
            'bg-amber-500/10 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
    },
    closed: {
        label: 'Closed',
        className:
            'bg-emerald-500/10 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
    },
};

/**
 * Badge status biaya. Label mengikuti PRD §7.1; warna disusun agar "Closed"
 * (lunas) dan "Open" (belum lunas) terbaca berbeda tanpa perlu ikon.
 */
export default function ExpenseStatusBadge({
    status,
}: {
    status: ExpenseStatusValue;
}) {
    const style = STATUS_STYLE[status] ?? STATUS_STYLE.open;

    return (
        <Badge
            className={`text-xs font-medium whitespace-nowrap ${style.className}`}
        >
            {style.label}
        </Badge>
    );
}
