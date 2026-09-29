<?php

namespace Modules\Purchasing\Enums;

enum PurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case ClosedByReturn = 'closed_by_return';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::PartiallyPaid => 'Disicil',
            self::Paid => 'Lunas',
            self::ClosedByReturn => 'Ditutup karena Retur',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isPaid(): bool
    {
        return $this === self::Paid;
    }

    public function isPartiallyPaid(): bool
    {
        return $this === self::PartiallyPaid;
    }

    public function isClosedByReturn(): bool
    {
        return $this === self::ClosedByReturn;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::ClosedByReturn || $this === self::Cancelled;
    }
}
