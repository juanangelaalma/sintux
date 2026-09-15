import { Chip } from '@heroui/react';
import { SALES_INVOICE_STATUS_LABEL } from '@/lib/sales/status';

type StatusColor = 'default' | 'accent' | 'success' | 'warning' | 'danger';

const STATUS_COLOR: Record<string, StatusColor> = {
    pending: 'warning',
    approved: 'success',
    cancelled: 'danger',
};

export default function SalesStatusBadge({ status }: { status: string }) {
    const label =
        SALES_INVOICE_STATUS_LABEL[
            status as keyof typeof SALES_INVOICE_STATUS_LABEL
        ] ?? status;

    return (
        <Chip
            color={STATUS_COLOR[status] ?? 'default'}
            size="sm"
            variant="primary"
            className="font-bold text-white shadow-xs"
        >
            {label}
        </Chip>
    );
}
