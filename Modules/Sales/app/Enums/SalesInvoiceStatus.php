<?php

namespace Modules\Sales\Enums;

enum SalesInvoiceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isApproved(): bool
    {
        return $this === self::Approved;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }
}
