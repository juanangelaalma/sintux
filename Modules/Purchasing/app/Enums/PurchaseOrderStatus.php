<?php

namespace Modules\Purchasing\Enums;

enum PurchaseOrderStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Sent = 'sent';
    case PartiallyReceived = 'partially_received';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Pending => 'Menunggu',
            self::Approved => 'Disetujui',
            self::Sent => 'Terkirim',
            self::PartiallyReceived => 'Sebagian Diterima',
            self::Received => 'Diterima',
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

    public function isSent(): bool
    {
        return $this === self::Sent;
    }

    public function isPartiallyReceived(): bool
    {
        return $this === self::PartiallyReceived;
    }

    public function isReceived(): bool
    {
        return $this === self::Received;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isFinal(): bool
    {
        return $this === self::Received || $this === self::Cancelled;
    }

    public function canSend(): bool
    {
        return $this === self::Approved;
    }

    public function canCancel(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Approved, self::Sent], true);
    }
}
