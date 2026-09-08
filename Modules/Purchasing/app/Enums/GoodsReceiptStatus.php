<?php

namespace Modules\Purchasing\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Diposting',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    public function canPost(): bool
    {
        return $this === self::Draft;
    }
}
