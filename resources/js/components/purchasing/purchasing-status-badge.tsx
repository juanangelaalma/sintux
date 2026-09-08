import { Chip } from '@heroui/react';
import { PURCHASING_STATUS_LABEL } from '@/lib/purchasing/status';

type StatusStyle = {
    label: string;
    color: 'default' | 'accent' | 'success' | 'warning' | 'danger';
};

const STATUS_COLOR: Record<string, StatusStyle['color']> = {
    draft: 'default',
    pending: 'warning',
    approved: 'accent',
    sent: 'accent',
    partially_received: 'warning',
    received: 'success',
    posted: 'success',
    ready: 'success',
    accepted: 'success',
    paid: 'success',
    cancelled: 'danger',
};

const STATUS_MAP: Record<string, StatusStyle> = Object.fromEntries(
    Object.entries(PURCHASING_STATUS_LABEL).map(([value, label]) => [
        value,
        { label, color: STATUS_COLOR[value] ?? 'default' },
    ]),
) as Record<string, StatusStyle>;

const DEFAULT_STYLE: StatusStyle = { label: 'Draft', color: 'default' };

export default function PurchasingStatusBadge({ status }: { status: string }) {
    const style = STATUS_MAP[status] ?? DEFAULT_STYLE;

    return (
        <Chip
            color={style.color}
            size="sm"
            variant="primary"
            className="font-bold text-white shadow-xs"
        >
            {style.label}
        </Chip>
    );
}
