<?php

namespace Modules\Purchasing\Enums;

enum PurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Paid => 'Lunas',
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

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isFinal(): bool
    {
        return $this === self::Paid || $this === self::Cancelled;
    }
}
