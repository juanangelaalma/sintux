<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseInvoice;

class GetPayableInvoices
{
    /**
     * Faktur yang masih bisa dibayar untuk satu supplier, beserta
     * outstanding = total − paid_amount − returned_amount.
     *
     * Public cross-module API milik Purchasing: Payment memakai ini untuk
     * mengisi form alokasi, tidak menyentuh tabel faktur langsung.
     *
     * @param  list<int>  $branchIds
     * @return list<array{
     *     id: int, number: string, supplier_id: int, branch_id: int,
     *     currency_code: string, total: float, paid_amount: float,
     *     returned_amount: float, outstanding: float, status: string
     * }>
     */
    public function execute(int $supplierId, array $branchIds = []): array
    {
        $query = PurchaseInvoice::query()
            ->where('supplier_id', $supplierId)
            ->whereIn('status', [
                PurchaseInvoiceStatus::Approved->value,
                PurchaseInvoiceStatus::PartiallyPaid->value,
            ]);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query
            ->orderBy('id')
            ->get()
            ->map(function (PurchaseInvoice $invoice): array {
                $total = (float) $invoice->total;
                $paid = (float) ($invoice->paid_amount ?? 0);
                $returned = (float) ($invoice->returned_amount ?? 0);
                $outstanding = max(0.0, $total - $paid - $returned);

                return [
                    'id' => (int) $invoice->id,
                    'number' => (string) $invoice->number,
                    'supplier_id' => (int) $invoice->supplier_id,
                    'branch_id' => (int) $invoice->branch_id,
                    'currency_code' => (string) $invoice->currency_code,
                    'total' => $total,
                    'paid_amount' => $paid,
                    'returned_amount' => $returned,
                    'outstanding' => $outstanding,
                    'status' => (string) ($invoice->status instanceof PurchaseInvoiceStatus
                        ? $invoice->status->value
                        : $invoice->status),
                ];
            })
            ->filter(fn (array $row): bool => $row['outstanding'] > 0.0001)
            ->values()
            ->all();
    }
}
