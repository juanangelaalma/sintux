<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseInvoice;

class ApplyInvoicePayment
{
    /**
     * Terapkan pembayaran ke satu faktur: naikkan paid_amount, transisi
     * status, tolak bila melebihi outstanding.
     *
     * Public cross-module API milik Purchasing. Ini satu-satunya jalan
     * Payment menyentuh data faktur (AGENTS: cross-module via Application
     * API). Idempoten per reference: reference yang sama tidak menambah
     * paid_amount dua kali.
     */
    public function execute(int $invoiceId, float $amount, string $referenceType = '', int $referenceId = 0): PurchaseInvoice
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Nominal pembayaran harus lebih dari 0.',
            ]);
        }

        return DB::transaction(function () use ($invoiceId, $amount, $referenceType, $referenceId): PurchaseInvoice {
            $invoice = PurchaseInvoice::whereKey($invoiceId)->lockForUpdate()->firstOrFail();

            if ($referenceType !== '' && $referenceId > 0) {
                $already = DB::table('purchase_payment_invoice_applies')
                    ->where('purchase_invoice_id', $invoiceId)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->lockForUpdate()
                    ->exists();

                if ($already) {
                    return $invoice;
                }
            }

            $outstanding = max(0.0, (float) $invoice->total - (float) ($invoice->paid_amount ?? 0) - (float) ($invoice->returned_amount ?? 0));

            if ($amount - $outstanding > 0.0001) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => 'Nominal melebihi outstanding faktur ('.number_format($outstanding, 0, '.', ',').').',
                ]);
            }

            $paidAfter = round((float) ($invoice->paid_amount ?? 0) + $amount, 4);
            $outstandingAfter = max(0.0, (float) $invoice->total - $paidAfter - (float) ($invoice->returned_amount ?? 0));

            $status = match (true) {
                $outstandingAfter <= 0.0001 => PurchaseInvoiceStatus::Paid,
                $paidAfter > 0.0001 => PurchaseInvoiceStatus::PartiallyPaid,
                default => $invoice->status,
            };

            $invoice->update([
                'paid_amount' => $paidAfter,
                'status' => $status,
            ]);

            if ($referenceType !== '' && $referenceId > 0) {
                DB::table('purchase_payment_invoice_applies')->insert([
                    'purchase_invoice_id' => $invoiceId,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'amount' => round($amount, 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $invoice;
        });
    }
}
