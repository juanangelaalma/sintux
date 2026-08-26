import { AlertCircle, CheckCircle2, Clock } from 'lucide-react';
import { KPI } from '@/components/ui/kpi';

export type PurchasingSummary = {
    unpaid_total?: number;
    unpaid_count?: number;
    overdue_total?: number;
    overdue_count?: number;
    paid_recent_total?: number;
    paid_recent_count?: number;
    // Backward compatibility for camelCase
    unpaidTotal?: number;
    unpaidCount?: number;
    overdueTotal?: number;
    overdueCount?: number;
    paymentsSentTotal?: number;
    paymentsSentCount?: number;
};

export default function PurchasingSummaryCards(props: PurchasingSummary) {
    const unpaidTotal = props.unpaid_total ?? props.unpaidTotal ?? 0;
    const unpaidCount = props.unpaid_count ?? props.unpaidCount ?? 0;
    const overdueTotal = props.overdue_total ?? props.overdueTotal ?? 0;
    const overdueCount = props.overdue_count ?? props.overdueCount ?? 0;
    const paidRecentTotal =
        props.paid_recent_total ?? props.paymentsSentTotal ?? 0;
    const paidRecentCount =
        props.paid_recent_count ?? props.paymentsSentCount ?? 0;

    return (
        <div className="grid grid-cols-1 gap-3 lg:grid-cols-3">
            {/* KPI 1: Faktur Belum Dibayar */}
            <KPI className="border border-amber-400/60 hover:border-amber-500 dark:border-amber-500/40">
                <KPI.Header>
                    <div className="flex items-center gap-2">
                        <KPI.Icon status="warning">
                            <AlertCircle className="size-4" />
                        </KPI.Icon>
                        <KPI.Title>Faktur Belum Dibayar</KPI.Title>
                    </div>
                    <KPI.Trend
                        count={unpaidCount}
                        label="Dokumen"
                        status="warning"
                    />
                </KPI.Header>

                <KPI.Content className="mt-2">
                    <KPI.Value value={unpaidTotal} />
                </KPI.Content>
            </KPI>

            {/* KPI 2: Faktur Telat Dibayar */}
            <KPI className="border border-rose-400/60 hover:border-rose-500 dark:border-rose-500/40">
                <KPI.Header>
                    <div className="flex items-center gap-2">
                        <KPI.Icon status="danger">
                            <Clock className="size-4" />
                        </KPI.Icon>
                        <KPI.Title>Faktur Telat Dibayar</KPI.Title>
                    </div>
                    <KPI.Trend
                        count={overdueCount}
                        label="Dokumen"
                        status="danger"
                    />
                </KPI.Header>

                <KPI.Content className="mt-2">
                    <KPI.Value value={overdueTotal} />
                </KPI.Content>
            </KPI>

            {/* KPI 3: Pelunasan 30 Hari Terakhir */}
            <KPI className="border border-emerald-400/60 hover:border-emerald-500 dark:border-emerald-500/40">
                <KPI.Header>
                    <div className="flex items-center gap-2">
                        <KPI.Icon status="success">
                            <CheckCircle2 className="size-4" />
                        </KPI.Icon>
                        <KPI.Title>Pelunasan 30 Hari Terakhir</KPI.Title>
                    </div>
                    <KPI.Trend
                        count={paidRecentCount}
                        label="Dokumen"
                        status="success"
                    />
                </KPI.Header>

                <KPI.Content className="mt-2">
                    <KPI.Value value={paidRecentTotal} />
                </KPI.Content>
            </KPI>
        </div>
    );
}
