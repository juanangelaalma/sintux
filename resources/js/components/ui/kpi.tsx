import { Button, Card, Chip } from '@heroui/react';
import type { ReactNode } from 'react';
import { formatCurrency } from '@/lib/format';

type StatusType = 'success' | 'warning' | 'danger';

type KPIProps = {
    children: ReactNode;
    className?: string;
};

type KPIHeaderProps = {
    children: ReactNode;
    className?: string;
};

type KPIIconProps = {
    status?: StatusType;
    children: ReactNode;
    className?: string;
};

type KPITitleProps = {
    children: ReactNode;
    className?: string;
};

type KPIContentProps = {
    children: ReactNode;
    className?: string;
};

type KPIValueProps = {
    value: number;
    formatted?: string;
    children?: (formatted: string) => ReactNode;
    className?: string;
};

type KPITrendProps = {
    count?: number;
    label?: string;
    status?: StatusType;
    className?: string;
};

type KPIActionsProps = {
    children?: ReactNode;
    className?: string;
    onPress?: () => void;
};

type KPIFooterProps = {
    children: ReactNode;
    className?: string;
};

export function KPI({ children, className = '' }: KPIProps) {
    return (
        <Card
            className={`kpi relative overflow-hidden p-3.5 shadow-2xs transition-all duration-200 hover:shadow-xs sm:p-4 ${className}`}
        >
            {children}
        </Card>
    );
}

function KPIHeader({ children, className = '' }: KPIHeaderProps) {
    return (
        <div
            className={`kpi__header flex items-center justify-between gap-2.5 ${className}`}
        >
            {children}
        </div>
    );
}

function KPIIcon({ status, children, className = '' }: KPIIconProps) {
    const statusBg =
        status === 'warning'
            ? 'bg-amber-500/10 text-amber-600 dark:bg-amber-400/10 dark:text-amber-400'
            : status === 'danger'
              ? 'bg-rose-500/10 text-rose-600 dark:bg-rose-400/10 dark:text-rose-400'
              : status === 'success'
                ? 'bg-emerald-500/10 text-emerald-600 dark:bg-emerald-400/10 dark:text-emerald-400'
                : 'bg-surface-secondary text-muted';

    return (
        <div
            data-status={status}
            className={`kpi__icon flex size-8 shrink-0 items-center justify-center rounded-lg font-semibold sm:size-9 ${statusBg} ${className}`}
        >
            {children}
        </div>
    );
}

function KPITitle({ children, className = '' }: KPITitleProps) {
    return (
        <dt
            className={`kpi__title text-[11px] font-semibold tracking-wider text-muted uppercase sm:text-xs ${className}`}
        >
            {children}
        </dt>
    );
}

function KPIContent({ children, className = '' }: KPIContentProps) {
    return (
        <div
            className={`kpi__content mt-2 flex items-baseline justify-between gap-2 ${className}`}
        >
            {children}
        </div>
    );
}

function KPIValue({
    value,
    formatted,
    children,
    className = '',
}: KPIValueProps) {
    const displayStr = formatted ?? formatCurrency(value);

    return (
        <dd
            className={`kpi__value text-lg font-extrabold tracking-tight text-foreground sm:text-xl ${className}`}
        >
            {children ? children(displayStr) : displayStr}
        </dd>
    );
}

function KPITrend({
    count,
    label,
    status = 'warning',
    className = '',
}: KPITrendProps) {
    return (
        <div className={`kpi__trend ${className}`}>
            <Chip
                color={status}
                size="sm"
                variant="soft"
                className="h-5 px-1.5 text-[11px] font-bold"
            >
                {count !== undefined ? `${count} ${label ?? ''}`.trim() : label}
            </Chip>
        </div>
    );
}

function KPIActions({ children, onPress, className = '' }: KPIActionsProps) {
    return (
        <div className={`kpi__actions ${className}`}>
            <Button
                isIconOnly
                size="sm"
                variant="tertiary"
                aria-label="Filter atau aksi KPI"
                onPress={onPress}
            >
                {children}
            </Button>
        </div>
    );
}

function KPIFooter({ children, className = '' }: KPIFooterProps) {
    return (
        <div
            className={`kpi__footer mt-2 border-t border-border/40 pt-2 text-[11px] text-muted ${className}`}
        >
            {children}
        </div>
    );
}

KPI.Header = KPIHeader;
KPI.Icon = KPIIcon;
KPI.Title = KPITitle;
KPI.Content = KPIContent;
KPI.Value = KPIValue;
KPI.Trend = KPITrend;
KPI.Actions = KPIActions;
KPI.Footer = KPIFooter;