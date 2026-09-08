<?php

namespace Modules\Purchasing\Enums;

enum PurchaseQuoteStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Terkirim',
            self::Accepted => 'Disetujui',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isSent(): bool
    {
        return $this === self::Sent;
    }

    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }

    public function isFinal(): bool
    {
        return $this === self::Accepted || $this === self::Cancelled;
    }

    public function canSend(): bool
    {
        return $this === self::Draft;
    }

    public function canAccept(): bool
    {
        return $this === self::Sent;
    }

    public function canCancel(): bool
    {
        return $this === self::Draft || $this === self::Sent;
    }
}
