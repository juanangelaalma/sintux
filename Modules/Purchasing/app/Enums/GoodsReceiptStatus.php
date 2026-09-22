<?php

namespace Modules\Purchasing\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'GRN (Draft)',
            self::Submitted => 'GRN (Menunggu HO)',
            self::Approved => 'GRN (Disetujui HO)',
            self::Rejected => 'GRN (Ditolak HO)',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function canSubmit(): bool
    {
        return $this === self::Draft;
    }

    public function canDecide(): bool
    {
        return $this === self::Submitted;
    }

    public function canRevise(): bool
    {
        return $this === self::Rejected;
    }
}
