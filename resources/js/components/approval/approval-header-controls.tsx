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

type CommentItem = {
    id: number;
    user_id: number;
    user_name: string;
    content: string;
    created_at?: string | null;
    date_label?: string;
    time_label?: string;
};

export type ApprovalStatusProps = {
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
    comments?: CommentItem[];
    comments_count?: number;
};

export default function ApprovalHeaderControls({
    approval,
    documentTitle,
}: {
    approval?: ApprovalStatusProps | null;
    documentTitle?: string;
}) {
    // Hooks must run unconditionally (Rules of Hooks) before any early return.
    const [isLogOpen, setIsLogOpen] = useState(false);
    const [isCommentOpen, setIsCommentOpen] = useState(false);
    const [isRejectDialogOpen, setIsRejectDialogOpen] = useState(false);
    const [commentContent, setCommentContent] = useState('');
    const [rejectComment, setRejectComment] = useState('');
    const [processing, setProcessing] = useState(false);

    if (!approval) {
        return null;
    }

    const handleApprove = () => {
        setProcessing(true);
        router.post(
            `/approval/mappings/${approval.mapping_id}/approve`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const handleReject = () => {
        setProcessing(true);
        router.post(
            `/approval/mappings/${approval.mapping_id}/reject`,
            { comment: rejectComment },
            {
                preserveScroll: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => {
                    setIsRejectDialogOpen(false);
                    setRejectComment('');
                },
            },
        );
    };

    const handleSendComment = (e: React.FormEvent) => {
        e.preventDefault();

        if (!commentContent.trim()) {
            return;
        }

        setProcessing(true);
        router.post(
            '/approval/comments',
            {
                transaction_type: approval.transaction_type,
                transaction_id: approval.transaction_id,
                content: commentContent,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onFinish: () => setProcessing(false),
                onSuccess: () => setCommentContent(''),
            },
        );
    };

    const commentsCount =
        approval.comments_count ?? approval.comments?.length ?? 0;

    return (
        <div className="relative flex items-center gap-2">
            {/* Setujui & Tolak Buttons (if user is active approver) */}
            {approval.can_user_approve && (
                <>
                    <button
                        type="button"
                        disabled={processing}
                        onClick={handleApprove}
                        className="rounded-lg border border-brand-600 bg-white px-4 py-1.5 text-sm font-semibold text-brand-600 shadow-xs hover:bg-brand-50 disabled:opacity-50 dark:bg-gray-900 dark:text-brand-400 dark:hover:bg-gray-800"
                    >
                        Setujui
                    </button>

                    <button
                        type="button"
                        onClick={() => setIsRejectDialogOpen(true)}
                        className="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-sm font-medium text-rose-600 shadow-xs hover:bg-rose-50 dark:border-rose-900 dark:bg-gray-900 dark:text-rose-400 dark:hover:bg-gray-800"
                    >
                        Tolak
                    </button>
                </>
            )}

            {/* Approval Log Button (Clock icon) */}
            <div className="relative">
                <button
                    type="button"
                    onClick={() => setIsLogOpen(!isLogOpen)}
                    aria-label="Approval Log"
                    className="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
                >
                    <svg
                        className="size-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        strokeWidth={2}
                    >
                        <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </button>

                {/* Popover Card (Image 1 style) */}
                {isLogOpen && (
                    <div className="absolute top-11 right-0 z-50 w-72 rounded-xl border border-gray-200 bg-white p-4 shadow-xl dark:border-gray-800 dark:bg-gray-900">
                        <div className="mb-3 border-b border-gray-100 pb-2 text-sm font-semibold text-gray-900 dark:border-gray-800 dark:text-white">
                            Approval Log
                        </div>

                        <div className="flex flex-col gap-4">
                            {approval.stages.map((stage, idx) => {
                                const isCurrent =
                                    stage.stage_order ===
                                        approval.current_stage_order &&
                                    approval.overall_status === 'pending';
                                const isPassed =
                                    stage.stage_order <
                                        approval.current_stage_order ||
                                    approval.overall_status === 'approved';

                                const lastApproveAction = stage.actions
                                    .filter((a) => a.action === 'approve')
                                    .pop();

                                return (
                                    <React.Fragment key={stage.id}>
                                        <div className="relative pl-6">
                                            {/* Stepper Dot */}
                                            <span
                                                className={`absolute top-1 left-0 size-3 rounded-full ${
                                                    isPassed
                                                        ? 'bg-emerald-500'
                                                        : isCurrent
                                                          ? 'animate-pulse bg-amber-500'
                                                          : 'bg-gray-300 dark:bg-gray-700'
                                                }`}
                                            />

                                            <div className="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                                Approval Tingkat{' '}
                                                {stage.stage_order}
                                            </div>

                                            <div className="mt-0.5 text-xs text-gray-600 dark:text-gray-400">
                                                {stage.approvers
                                                    .map((a) => a.name)
                                                    .join(', ')}
                                            </div>

                                            <div className="mt-1 text-[11px] font-medium text-gray-500">
                                                {isPassed &&
                                                lastApproveAction ? (
                                                    <span className="text-emerald-600 dark:text-emerald-400">
                                                        Disetujui -{' '}
                                                        {lastApproveAction.acted_at
                                                            ? new Date(
                                                                  lastApproveAction.acted_at,
                                                              ).toLocaleDateString(
                                                                  'id-ID',
                                                                  {
                                                                      day: '2-digit',
                                                                      month: 'short',
                                                                      year: 'numeric',
                                                                  },
                                                              )
                                                            : ''}
                                                    </span>
                                                ) : isCurrent ? (
                                                    <span className="text-amber-600 dark:text-amber-400">
                                                        Menunggu
                                                    </span>
                                                ) : (
                                                    <span className="text-gray-400">
                                                        Belum diproses
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        {idx < approval.stages.length - 1 && (
                                            <div className="ml-7 text-[10px] font-bold text-gray-400 uppercase">
                                                Kemudian
                                            </div>
                                        )}
                                    </React.Fragment>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            {/* Comment Icon Button with Badge */}
            <button
                type="button"
                onClick={() => setIsCommentOpen(true)}
                aria-label="Comments"
                className="relative flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-white"
            >
                <svg
                    className="size-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                    strokeWidth={2}
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                </svg>
                {commentsCount > 0 && (
                    <span className="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-brand-600 px-1 text-[10px] font-bold text-white">
                        {commentsCount}
                    </span>
                )}
            </button>

            {/* Reject Confirmation Dialog */}
            {isRejectDialogOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900">
                        <h3 className="text-base font-bold text-gray-900 dark:text-white">
                            Tolak Persetujuan Transaksi
                        </h3>
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Transaksi ini akan ditolak dan dikembalikan ke
                            pembuat.
                        </p>

                        <div className="my-4">
                            <label className="mb-1 block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Alasan / Catatan Penolakan (opsional):
                            </label>
                            <textarea
                                value={rejectComment}
                                onChange={(e) =>
                                    setRejectComment(e.target.value)
                                }
                                placeholder="Masukkan alasan penolakan..."
                                className="w-full rounded-xl border border-gray-300 p-2.5 text-sm text-gray-800 focus:border-rose-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                                rows={3}
                            />
                        </div>

                        <div className="flex items-center justify-end gap-3">
                            <button
                                type="button"
                                onClick={() => setIsRejectDialogOpen(false)}
                                className="rounded-lg border border-gray-300 px-4 py-2 text-xs font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Batal
                            </button>
                            <button
                                type="button"
                                onClick={handleReject}
                                className="rounded-lg bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700"
                            >
                                Tolak Transaksi
                            </button>
                        </div>
                    </div>
                </div>
            )}

            {/* Comments Modal (Image 2 style) */}
            {isCommentOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-lg rounded-2xl border border-gray-200 bg-white p-6 shadow-2xl dark:border-gray-800 dark:bg-gray-900">
                        {/* Modal Header */}
                        <div className="flex items-center justify-between border-b border-gray-100 pb-4 dark:border-gray-800">
                            <h3 className="text-base font-bold text-gray-900 dark:text-white">
                                Komentar di {approval.transaction_type_label} #
                                {documentTitle ??
                                    approval.document_number ??
                                    approval.transaction_id}
                            </h3>
                            <button
                                type="button"
                                onClick={() => setIsCommentOpen(false)}
                                className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
                            >
                                ✕
                            </button>
                        </div>

                        {/* Comments Body */}
                        <div className="my-4 flex max-h-80 min-h-48 flex-col gap-4 overflow-y-auto pr-1">
                            {!approval.comments ||
                            approval.comments.length === 0 ? (
                                <div className="my-auto text-center text-xs text-gray-400">
                                    Belum ada komentar pada transaksi ini.
                                </div>
                            ) : (
                                approval.comments.map((c) => {
                                    const initials = c.user_name
                                        ? c.user_name
                                              .split(' ')
                                              .map((n) => n[0])
                                              .join('')
                                              .substring(0, 2)
                                              .toUpperCase()
                                        : 'U';

                                    return (
                                        <React.Fragment key={c.id}>
                                            {c.date_label && (
                                                <div className="my-1 text-center text-[10px] font-medium text-gray-400">
                                                    {c.date_label}
                                                </div>
                                            )}

                                            <div className="flex items-start justify-end gap-3">
                                                <div className="max-w-[80%] rounded-2xl bg-brand-50 p-3 text-xs text-gray-800 dark:bg-brand-950/40 dark:text-gray-200">
                                                    <div className="mb-0.5 font-semibold text-brand-600 dark:text-brand-400">
                                                        {c.user_name}
                                                    </div>
                                                    <p>{c.content}</p>
                                                    <div className="mt-1 text-right text-[10px] text-gray-400">
                                                        {c.time_label}
                                                    </div>
                                                </div>
                                                <div className="flex size-7 items-center justify-center rounded-full bg-brand-600 text-[10px] font-bold text-white uppercase">
                                                    {initials}
                                                </div>
                                            </div>
                                        </React.Fragment>
                                    );
                                })
                            )}
                        </div>

                        {/* Modal Footer / Input */}
                        <form
                            onSubmit={handleSendComment}
                            className="flex items-center gap-2 border-t border-gray-100 pt-2 dark:border-gray-800"
                        >
                            <input
                                type="text"
                                value={commentContent}
                                onChange={(e) =>
                                    setCommentContent(e.target.value)
                                }
                                placeholder="Masukkan komentar Anda"
                                className="flex-1 rounded-xl border border-gray-300 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 focus:border-brand-500 focus:outline-hidden dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                            <button
                                type="submit"
                                disabled={processing || !commentContent.trim()}
                                className="rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 disabled:opacity-50"
                            >
                                Kirim
                            </button>
                        </form>
                    </div>
                </div>
            )}
        </div>
    );
}
