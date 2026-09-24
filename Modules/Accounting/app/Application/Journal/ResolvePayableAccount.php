<?php

namespace Modules\Accounting\Application\Journal;

use Modules\Accounting\Models\ChartOfAccount;

class ResolvePayableAccount
{
    public function __construct(
        private readonly DefaultPayableAccount $defaultPayableAccount,
    ) {}

    /**
     * Resolusi akun hutang supplier: mapping per-supplier bila ada,
     * fallback ke default (2101 Utang Usaha).
     *
     * Seam v1: tabel/UI konfigurasi mapping menyusul (lihat spec
     * Payment); saat ini selalu fallback default agar Payment dan
     * Retur bisa menjurnal tanpa menunggu konfigurasi tersebut.
     */
    public function execute(?int $supplierId = null): ChartOfAccount
    {
        return $this->defaultPayableAccount->execute();
    }
}
