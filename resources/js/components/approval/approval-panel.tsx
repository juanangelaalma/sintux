import { router } from '@inertiajs/react';
import React, { useState } from 'react';

type Approver = {
    id: number;
    name: string;
    email: string;
};

type Action = {
    id: number;
    user_id: number;
    action: 'approve' | 'reject';
    comment?: string | null;
    acted_at?: string | null;
};

type Stage = {
    id: number;
    stage_order: number;
    approval_type: 'any' | 'all';
    approvers: Approver[];
    actions: Action[];
};

export type ApprovalStatus = {
    mapping_id: number;
    rule_id: number;
    rule_name: string;
    transaction_type: string;
    transaction_type_label: string;
    transaction_id: number;
    document_number?: string | null;
    creator_id: number;
    creator_name?: string | null;
    current_stage_order: number;
    overall_status: 'pending' | 'approved' | 'rejected';
    can_user_approve: boolean;
    stages: Stage[];
    mapped_at?: string | null;
};

export default function ApprovalPanel({
    approval,
}: {
    approval?: ApprovalStatus | null;
}) {
    const [comment, setComment] = useState('');
    const [processing, setProcessing] = useState(false);

    if (!approval) {
        return null;
    }

    const handleAction = (action: 'approve' | 'reject') => {
        setProcessing(true);
        router.post(
            `/approval/mappings/${approval.mapping_id}/${action}`,
            { comment },
            {
                onFinish: () => setProcessing(false),
                onSuccess: () => setComment(''),
            },
        );
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'approved':
                return (
                    <span className="inline-flex items-center rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20 dark:bg-emerald-950/40 dark:text-emerald-400">
                        Disetujui
                    </span>
                );
            case 'rejected':
                return (
                    <span className="inline-flex items-center rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20 dark:bg-rose-950/40 dark:text-rose-400">
                        Ditolak
                    </span>
                );
            default:
                return (
                    <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700 ring-1 ring-amber-600/20 dark:bg-amber-950/40 dark:text-amber-400">
                        Menunggu Approval
                    </span>
                );
        }
    };

    return (
        <div className="rounded-xl border border-gray-200 bg-white p-5 shadow-xs dark:border-gray-800 dark:bg-gray-900">
            <div className="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
                <div>
                    <h3 className="text-base font-semibold text-gray-900 dark:text-white">
                        Alur Persetujuan (Approval)
                    </h3>
                    <p className="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Aturan:{' '}
                        <span className="font-medium text-gray-700 dark:text-gray-300">
                            {approval.rule_name}
                        </span>
                    </p>
                </div>
                <div>{getStatusBadge(approval.overall_status)}</div>
            </div>

            <div className="mt-4 flex flex-col gap-4">
                {approval.stages.map((stage) => {
                    const isCurrent =
                        stage.stage_order === approval.current_stage_order &&
                        approval.overall_status === 'pending';
                    const isPassed =
                        stage.stage_order < approval.current_stage_order ||
                        approval.overall_status === 'approved';

                    return (
                        <div
                            key={stage.id}
                            className={`rounded-lg border p-4 transition-colors ${
                                isCurrent
                                    ? 'border-brand-500/30 bg-brand-50/20 dark:border-brand-500/30 dark:bg-brand-950/10'
                                    : isPassed
                                      ? 'border-gray-200 bg-gray-50/50 dark:border-gray-800 dark:bg-gray-800/30'
                                      : 'border-gray-100 opacity-60 dark:border-gray-800/50'
                            }`}
                        >
                            <div className="mb-2 flex items-center justify-between">
                                <span className="text-xs font-bold tracking-wider text-gray-700 uppercase dark:text-gray-300">
                                    Tahap {stage.stage_order} (
                                    {stage.approval_type === 'any'
                                        ? 'Salah Satu'
                                        : 'Semua'}
                                    )
                                </span>
                                {isPassed && (
                                    <span className="flex items-center gap-1 text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                        ✓ Selesai
                                    </span>
                                )}
                                {isCurrent && (
                                    <span className="animate-pulse text-xs font-semibold text-brand-600 dark:text-brand-400">
                                        ● Sedang Berjalan
                                    </span>
                                )}
                            </div>

                            <div className="mb-2 text-xs text-gray-600 dark:text-gray-400">
                                Approver:{' '}
                                {stage.approvers.map((a) => a.name).join(', ')}
                            </div>

                            {stage.actions.length > 0 && (
                                <div className="mt-2 flex flex-col gap-1.5 border-t border-gray-100 pt-2 dark:border-gray-800">
                                    {stage.actions.map((act) => {
                                        const approverName =
                                            stage.approvers.find(
                                                (a) => a.id === act.user_id,
                                            )?.name ?? `User #${act.user_id}`;

                                        return (
                                            <div
                                                key={act.id}
                                                className="flex items-start justify-between rounded border border-gray-100 bg-white p-2 text-xs dark:border-gray-800 dark:bg-gray-900"
                                            >
                                                <div>
                                                    <span className="font-semibold text-gray-800 dark:text-gray-200">
                                                        {approverName}
                                                    </span>
                                                    :{' '}
                                                    <span
                                                        className={
                                                            act.action ===
                                                            'approve'
                                                                ? 'font-medium text-emerald-600'
                                                                : 'font-medium text-rose-600'
                                                        }
                                                    >
                                                        {act.action ===
                                                        'approve'
                                                            ? 'Menyetujui'
                                                            : 'Menolak'}
                                                    </span>
                                                    {act.comment && (
                                                        <p className="mt-0.5 text-gray-500 italic">
                                                            "{act.comment}"
                                                        </p>
                                                    )}
                                                </div>
                                                <span className="text-[10px] text-gray-400">
                                                    {act.acted_at
                                                        ? new Date(
                                                              act.acted_at,
                                                          ).toLocaleDateString(
                                                              'id-ID',
                                                          )
                                                        : ''}
                                                </span>
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    );
                })}

                {approval.can_user_approve && (
                    <div className="mt-4 flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-gray-800">
                        <label className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                            Catatan / Komentar Persetujuan (opsional):
                        </label>
                        <textarea
                            value={comment}
                            onChange={(e) => setComment(e.target.value)}
                            placeholder="Tulis alasan atau catatan..."
                            className="w-full rounded-lg border border-gray-300 p-2 text-sm focus:border-brand-500 focus:outline-none dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            rows={2}
                        />
                        <div className="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => handleAction('reject')}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-medium text-white hover:bg-rose-700 disabled:opacity-50"
                            >
                                Tolak Transaksi
                            </button>
                            <button
                                type="button"
                                disabled={processing}
                                onClick={() => handleAction('approve')}
                                className="rounded-lg bg-emerald-600 px-4 py-2 text-xs font-medium text-white hover:bg-emerald-700 disabled:opacity-50"
                            >
                                Setujui Transaksi
                            </button>
                        </div>
                    </div>
                )}
            </div>
        </div>
    );
}
