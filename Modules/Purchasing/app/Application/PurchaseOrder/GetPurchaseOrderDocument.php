<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use App\Models\User;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContact;
use Modules\Purchasing\Models\PurchaseOrder;

class GetPurchaseOrderDocument
{
    public function __construct(
        private readonly GetPurchaseOrderDetail $getPurchaseOrderDetail,
        private readonly GetContact $getContact,
    ) {}

    /**
     * Build the print/PDF projection for a purchase order.
     *
     * Supplier and branch info are resolved through their owning module's
     * public Application API; the PO's own snapshot fields remain the
     * fallback so historical documents stay correct.
     *
     * @return array{order: PurchaseOrder, supplier: array<string, mixed>|null, branch: object|null, companyName: string}
     */
    public function execute(int $id, User $user, string $tenantId): array
    {
        $order = $this->getPurchaseOrderDetail->execute($id);

        $branchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);

        $supplier = null;
        try {
            $supplier = $this->getContact->execute((int) $order->supplier_id, $branchIds, 'supplier');
        } catch (\Throwable) {
            $supplier = null;
        }

        $branch = collect(CompanyAccess::accessibleBranches($user, $tenantId))
            ->firstWhere('id', (int) $order->branch_id);

        $tenantName = tenant('name');
        $companyName = is_string($tenantName) ? $tenantName : '';

        return [
            'order' => $order,
            'supplier' => $supplier,
            'branch' => $branch,
            'companyName' => $companyName,
        ];
    }
}
