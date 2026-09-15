<?php

namespace Modules\Sales\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a sales invoice becomes Approved (jalur auto-final
 * maupun finalize approval). Konsumen (mis. Accounting) bereaksi
 * membentuk jurnal dari snapshot faktur, bukan dari model internal Sales.
 */
class SalesInvoiceApproved
{
    use Dispatchable;

    public function __construct(
        public readonly int $salesInvoiceId,
    ) {}
}
