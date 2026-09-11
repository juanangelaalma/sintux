<?php

namespace Modules\Warehouse\Enums;

enum StockTransferStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Rejected = 'rejected';
    case Shipped = 'shipped';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::PendingApproval => 'Menunggu Persetujuan HO',
            self::Rejected => 'Ditolak',
            self::Shipped => 'Dikirim (Dalam Perjalanan)',
            self::Received => 'Diterima (Selesai)',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isPendingApproval(): bool
    {
        return $this === self::PendingApproval;
    }

    public function isRejected(): bool
    {
        return $this === self::Rejected;
    }

    public function isShipped(): bool
    {
        return $this === self::Shipped;
    }

    public function isReceived(): bool
    {
        return $this === self::Received;
    }

    public function isShippable(): bool
    {
        return $this === self::Draft;
    }

    public function isReceivable(): bool
    {
        return $this === self::Shipped;
    }

    public function isFinal(): bool
    {
        return $this === self::Received || $this === self::Rejected;
    }
}
