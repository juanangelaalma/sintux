<?php

namespace Modules\Purchasing\Enums;

enum JoinPurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Siap',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isReady(): bool
    {
        return $this === self::Ready;
    }

    public function canMarkReady(): bool
    {
        return $this === self::Draft;
    }
}
