<?php

namespace Modules\Warehouse\Services;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        string $message = 'Insufficient stock to fulfill the transfer'
    ) {
        parent::__construct($message);
    }
}