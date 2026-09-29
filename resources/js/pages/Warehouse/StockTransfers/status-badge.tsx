import { Chip } from '@heroui/react';

type StatusColor = 'default' | 'accent' | 'success' | 'warning' | 'danger';

type StatusStyle = {
    label: string;
    color: StatusColor;
};

const STATUS_STYLE: Record<string, StatusStyle> = {
    draft: { label: 'DRAFT', color: 'default' },
    pending_approval: {
        label: 'MENUNGGU PERSETUJUAN HO',
        color: 'warning',
    },
    rejected: { label: 'DITOLAK HO', color: 'danger' },
    shipped: { label: 'SHIPPED (DALAM PERJALANAN)', color: 'accent' },
    received: { label: 'RECEIVED (SELESAI)', color: 'success' },
    cancelled: { label: 'DIBATALKAN', color: 'danger' },
};

const FALLBACK: StatusStyle = {
    label: 'STATUS TIDAK DIKENAL',
    color: 'default',
};

export default function StockTransferStatusBadge({
    status,
}: {
    status: string;
}) {
    const style = STATUS_STYLE[status] ?? FALLBACK;

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
