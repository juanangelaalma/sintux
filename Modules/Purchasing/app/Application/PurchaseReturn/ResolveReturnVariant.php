<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Modules\Product\Application\Variant\GetVariantForBranch;

class ResolveReturnVariant
{
    public function __construct(
        private readonly GetVariantForBranch $branchVariants,
    ) {}

    /**
     * Varian gudang yang di-consume untuk satu baris retur.
     *
     * Tanpa transfer: varian faktur apa adanya. Dengan transfer: padanan
     * SKU+warna di cabang gudang (cermin hasil receive); null bila tak ada
     * (pemanggil memblokir baris dengan pesan jelas).
     */
    public function execute(int $invoiceVariantId, int $warehouseBranchId, ?int $transferId): ?int
    {
        if ($transferId === null) {
            return $invoiceVariantId;
        }

        $resolved = $this->branchVariants->execute($invoiceVariantId, $warehouseBranchId);

        if ($resolved === null) {
            return null;
        }

        return $resolved['variant_id'];
    }
}
