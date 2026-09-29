import type { RequestPayload } from '@inertiajs/core';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import ApprovalHeaderControls from '@/components/approval/approval-header-controls';
import type { ApprovalStatusProps } from '@/components/approval/approval-header-controls';
import CompanyLayout from '@/layouts/company/company-layout';
import { formatDate } from '@/lib/format';
import { ConfirmDialog, ReceiveDialog, ReturnDialog } from './action-dialogs';
import type { TransferErrors } from './action-dialogs';
import { NEXT_STEP_HINT, TRANSFER_STEPS } from './helpers';
import StockTransferStatusBadge from './status-badge';
import TransferTabs from './transfer-tabs';
import type { PurchaseLineage, ReturnOption, StockTransfer } from './types';

type Props = {
    stockTransfer: StockTransfer;
    canApprove?: boolean;
    canReturn?: boolean;
    returnOptions?: ReturnOption[];
    purchaseLineage?: PurchaseLineage | null;
    approval?: ApprovalStatusProps | null;
};

type Busy = 'ship' | 'approve' | null;

function Stepper({ status }: { status: string }) {
    const current = TRANSFER_STEPS.findIndex((step) => step.key === status);

    if (current === -1) {
        return null;
    }

    return (
        <ol
            aria-label="Progres transfer"
            className="flex flex-wrap items-center gap-2 text-xs font-semibold"
        >
            {TRANSFER_STEPS.map((step, index) => (
                <li key={step.key} className="flex items-center gap-2">
                    <span
                        aria-current={index === current ? 'step' : undefined}
                        className={`flex items-center gap-1.5 rounded-full px-2.5 py-1 ${
                            index < current
                                ? 'bg-success-soft text-success'
                                : index === current
                                  ? 'bg-accent text-accent-foreground'
                                  : 'bg-surface-secondary text-muted'
                        }`}
                    >
                        {index < current ? '✓' : index + 1} {step.label}
                    </span>
                    {index < TRANSFER_STEPS.length - 1 && (
                        <span aria-hidden className="h-px w-6 bg-border" />
                    )}
                </li>
            ))}
        </ol>
    );
}

function SummaryField({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div>
            <p className="text-xs font-semibold text-muted">{label}</p>
            <div className="mt-1 font-semibold text-foreground">{children}</div>
        </div>
    );
}

function Sub({ children }: { children: ReactNode }) {
    return <p className="text-xs font-normal text-muted">{children}</p>;
}

function TextLink({ href, children }: { href: string; children: ReactNode }) {
    return (
        <Link href={href} className="text-accent hover:underline">
            {children}
        </Link>
    );
}

export default function StockTransferShow({
    stockTransfer,
    canApprove = false,
    canReturn = false,
    returnOptions = [],
    purchaseLineage = null,
    approval = null,
}: Props) {
    const errors = usePage().props.errors as TransferErrors;
    const [busy, setBusy] = useState<Busy>(null);

    const documentLabel = stockTransfer.number ?? `#${stockTransfer.id}`;
    const basePath = `/warehouse/stock-transfers/${stockTransfer.id}`;
    const canDecide =
        !approval && canApprove && stockTransfer.status === 'pending_approval';
    const hint = NEXT_STEP_HINT[stockTransfer.status];

    const post = (
        action: 'ship' | 'approve',
        kind: Exclude<Busy, null>,
        data: RequestPayload = {},
    ) => {
        setBusy(kind);
        router.post(`${basePath}/${action}`, data, {
            onFinish: () => setBusy(null),
        });
    };

    return (
        <CompanyLayout>
            <Head title={`Detail Transfer ${documentLabel}`} />

            <div className="w-full space-y-6">
                <div className="space-y-4 border-b border-border pb-4">
                    <Link
                        href="/warehouse/stock-transfers"
                        className="text-xs font-semibold text-accent hover:underline"
                    >
                        ← Gudang / Transfer Stok
                    </Link>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div className="space-y-2">
                            <div className="flex flex-wrap items-center gap-3">
                                <h1 className="text-2xl font-bold text-foreground">
                                    Transfer Stok {documentLabel}
                                </h1>
                                <StockTransferStatusBadge
                                    status={stockTransfer.status}
                                />
                            </div>
                            <Stepper status={stockTransfer.status} />
                            {hint && (
                                <p className="text-sm text-muted">{hint}</p>
                            )}
                        </div>

                        <div className="flex flex-wrap items-center gap-2">
                            <ApprovalHeaderControls
                                approval={approval}
                                documentTitle={documentLabel}
                            />

                            {canReturn && (
                                <ReturnDialog
                                    transfer={stockTransfer}
                                    options={returnOptions}
                                    errors={errors}
                                />
                            )}

                            {canDecide && (
                                <>
                                    <ConfirmDialog
                                        triggerLabel="Tolak"
                                        triggerVariant="danger-soft"
                                        status="danger"
                                        title="Tolak Transfer Stok"
                                        description="Transfer akan berstatus ditolak dan tidak dapat dikirim."
                                        confirmLabel="Ya, Tolak"
                                        confirmVariant="danger"
                                        isPending={busy === 'approve'}
                                        onConfirm={() =>
                                            post('approve', 'approve', {
                                                decision: 'reject',
                                            })
                                        }
                                    />
                                    <ConfirmDialog
                                        triggerLabel="Setujui"
                                        title="Setujui Transfer Stok"
                                        description="Transfer akan berstatus draft dan siap dikirim. Kecukupan stok gudang asal dicek saat persetujuan."
                                        confirmLabel="Ya, Setujui"
                                        isPending={busy === 'approve'}
                                        onConfirm={() =>
                                            post('approve', 'approve', {
                                                decision: 'approve',
                                            })
                                        }
                                    />
                                </>
                            )}

                            {stockTransfer.status === 'draft' && (
                                <ConfirmDialog
                                    triggerLabel="Kirim Transfer"
                                    title="Konfirmasi Pengiriman Stok"
                                    description="Stok di gudang asal langsung berkurang memakai FIFO costing. Tindakan ini tidak bisa dibatalkan."
                                    confirmLabel="Ya, Kirim Sekarang"
                                    isPending={busy === 'ship'}
                                    onConfirm={() => post('ship', 'ship')}
                                />
                            )}

                            {stockTransfer.status === 'shipped' && (
                                <ReceiveDialog
                                    transfer={stockTransfer}
                                    errors={errors}
                                />
                            )}
                        </div>
                    </div>
                </div>

                {typeof errors.stock_transfer === 'string' && (
                    <div
                        role="alert"
                        className="rounded-lg border border-danger/30 bg-danger-soft p-4 text-sm text-danger"
                    >
                        {errors.stock_transfer}
                    </div>
                )}

                {typeof errors.approval === 'string' && (
                    <div
                        role="alert"
                        className="rounded-lg border border-warning/30 bg-warning-soft p-4 text-sm text-warning"
                    >
                        {errors.approval}
                        <p className="mt-1 text-xs">
                            Transfer ini diatur oleh Aturan Approval. Lakukan
                            persetujuan via Inbox Approval.
                        </p>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-4 rounded-xl border border-border bg-surface p-6 text-sm md:grid-cols-3">
                    <SummaryField label="Gudang Asal (Pengirim)">
                        {stockTransfer.from_warehouse?.name ?? '-'}
                        <Sub>
                            Cabang:{' '}
                            {stockTransfer.from_warehouse?.branch?.name ?? '-'}
                        </Sub>
                    </SummaryField>

                    <SummaryField label="Gudang Tujuan (Penerima)">
                        {stockTransfer.to_warehouse?.name ?? '-'}
                        <Sub>
                            Cabang:{' '}
                            {stockTransfer.to_warehouse?.branch?.name ?? '-'}
                        </Sub>
                    </SummaryField>

                    <SummaryField label="Tanggal Dibuat">
                        {formatDate(stockTransfer.created_at)}
                    </SummaryField>

                    {stockTransfer.shipped_at && (
                        <SummaryField label="Dikirim">
                            {formatDate(stockTransfer.shipped_at)}
                            <Sub>
                                oleh{' '}
                                {stockTransfer.shipped_by_user?.name ??
                                    `User #${stockTransfer.shipped_by}`}
                            </Sub>
                        </SummaryField>
                    )}

                    {stockTransfer.received_at && (
                        <SummaryField label="Diterima">
                            {formatDate(stockTransfer.received_at)}
                            <Sub>
                                oleh{' '}
                                {stockTransfer.received_by_user?.name ??
                                    `User #${stockTransfer.received_by}`}
                            </Sub>
                        </SummaryField>
                    )}

                    {stockTransfer.stock_request_id && (
                        <SummaryField label="Stock Request">
                            #{stockTransfer.stock_request_id}
                        </SummaryField>
                    )}

                    {stockTransfer.source_type?.endsWith('GoodsReceipt') &&
                        stockTransfer.source_id != null && (
                            <SummaryField label="Sumber">
                                <TextLink
                                    href={`/purchasing/grn-inbox/${stockTransfer.source_id}`}
                                >
                                    Lihat approval GRN
                                </TextLink>
                            </SummaryField>
                        )}

                    {stockTransfer.origin_transfer && (
                        <SummaryField label="Retur Dari Transfer">
                            <TextLink
                                href={`/warehouse/stock-transfers/${stockTransfer.origin_transfer.id}`}
                            >
                                {stockTransfer.origin_transfer.number ??
                                    `#${stockTransfer.origin_transfer.id}`}
                            </TextLink>
                        </SummaryField>
                    )}
                </div>

                <TransferTabs
                    transfer={stockTransfer}
                    lineage={purchaseLineage}
                />
            </div>
        </CompanyLayout>
    );
}
