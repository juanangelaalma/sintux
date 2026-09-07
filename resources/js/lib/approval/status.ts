export const ApprovalStatus = {
    Pending: 'pending',
    Approved: 'approved',
    Rejected: 'rejected',
} as const;

export type ApprovalStatusType = (typeof ApprovalStatus)[keyof typeof ApprovalStatus];

export const ApprovalActionType = {
    Approve: 'approve',
    Reject: 'reject',
} as const;

export type ApprovalActionType = (typeof ApprovalActionType)[keyof typeof ApprovalActionType];

export const APPROVAL_STATUS_LABEL: Record<ApprovalStatusType, string> = {
    [ApprovalStatus.Pending]: 'Menunggu',
    [ApprovalStatus.Approved]: 'Disetujui',
    [ApprovalStatus.Rejected]: 'Ditolak',
};

export const APPROVAL_STATUS_DOT_CLASS: Record<ApprovalStatusType, string> = {
    [ApprovalStatus.Pending]: 'animate-pulse bg-amber-500',
    [ApprovalStatus.Approved]: 'bg-emerald-500',
    [ApprovalStatus.Rejected]: 'bg-rose-500',
};

export const APPROVAL_STAGE_STATUS = {
    Current: 'current',
    Passed: 'passed',
    Upcoming: 'upcoming',
} as const;

export function getStageStatus(stageOrder: number, currentStageOrder: number, overallStatus: ApprovalStatusType): string {
    if (overallStatus === ApprovalStatus.Approved) {
        return APPROVAL_STAGE_STATUS.Passed;
    }

    if (stageOrder < currentStageOrder) {
        return APPROVAL_STAGE_STATUS.Passed;
    }

    if (stageOrder === currentStageOrder && overallStatus === ApprovalStatus.Pending) {
        return APPROVAL_STAGE_STATUS.Current;
    }

    if (overallStatus === ApprovalStatus.Rejected && stageOrder === currentStageOrder) {
        return APPROVAL_STAGE_STATUS.Current;
    }

    return APPROVAL_STAGE_STATUS.Upcoming;
}
