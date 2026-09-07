import { Chip } from '@heroui/react';

type StatusStyle = {
    label: string;
    color: 'default' | 'accent' | 'success' | 'warning' | 'danger';
};

const STATUS_MAP: Record<string, StatusStyle> = {
    unpaid: { label: 'Belum Lunas', color: 'warning' },
    pending: { label: 'Belum Dibayar', color: 'warning' },
    open: { label: 'Belum Lunas', color: 'warning' },
    draft: { label: 'Draft', color: 'default' },
    approved: { label: 'Disetujui', color: 'accent' },
    sent: { label: 'Terkirim', color: 'accent' },
    partially_received: { label: 'Sebagian Diterima', color: 'warning' },
    received: { label: 'Diterima', color: 'success' },
    posted: { label: 'Dipotong', color: 'success' },
    ready: { label: 'Siap', color: 'success' },
    accepted: { label: 'Disetujui', color: 'success' },
    overdue: { label: 'Lewat Jatuh Tempo', color: 'danger' },
    cancelled: { label: 'Dibatalkan', color: 'danger' },
    paid: { label: 'Lunas', color: 'success' },
    completed: { label: 'Selesai', color: 'success' },
};

const DEFAULT_STYLE: StatusStyle = { label: 'Belum Lunas', color: 'warning' };

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
