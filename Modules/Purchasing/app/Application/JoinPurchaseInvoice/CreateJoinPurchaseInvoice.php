<?php

namespace Modules\Purchasing\Application\JoinPurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Enums\JoinPurchaseInvoiceStatus;
use Modules\Purchasing\Models\JoinPurchaseInvoice;

class CreateJoinPurchaseInvoice
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $branchCode): JoinPurchaseInvoice
    {
        return DB::transaction(function () use ($data, $branchCode) {
            $sequence = JoinPurchaseInvoice::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'JOIN-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $totalAmount = 0.0;
            foreach ($data['items'] as $item) {
                $totalAmount += (float) $item['invoice_total'];
            }

            $join = JoinPurchaseInvoice::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'status' => JoinPurchaseInvoiceStatus::Draft,
                'join_date' => $data['join_date'],
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'total_amount' => $totalAmount,
            ]);

            foreach ($data['items'] as $item) {
                $join->items()->create([
                    'purchase_invoice_id' => $item['purchase_invoice_id'],
                    'supplier_id' => $item['supplier_id'],
                    'invoice_number' => $item['invoice_number'],
                    'supplier_name' => $item['supplier_name'],
                    'invoice_total' => $item['invoice_total'],
                ]);
            }

            return $join->load('items');
        });
    }
}
