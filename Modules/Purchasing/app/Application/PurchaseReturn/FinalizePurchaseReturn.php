<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\Journal\RecordJournal;
use Modules\Accounting\Application\Journal\ResolvePayableAccount;
use Modules\Accounting\Application\TaxCalculator;
use Modules\Accounting\Application\TaxQuery;
use Modules\Product\Application\Variant\GetVariantReturnProfile;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Enums\PurchaseReturnStatus;
use Modules\Purchasing\Models\PurchaseInvoice;
use Modules\Purchasing\Models\PurchaseInvoiceItem;
use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Purchasing\Models\SupplierDebitMemo;
use Modules\Warehouse\Application\StockIssue\IssueReturnStock;

class FinalizePurchaseReturn
{
    public function __construct(
        private readonly GetVariantReturnProfile $returnProfiles,
        private readonly IssueReturnStock $issueReturnStock,
        private readonly TaxQuery $taxQuery,
        private readonly TaxCalculator $taxCalculator,
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly ResolvePayableAccount $payableAccounts,
        private readonly RecordJournal $recordJournal,
    ) {}

    /**
     * Finalisasi retur: stok keluar (tracked) + jurnal + tracking faktur
     * + status + debit memo bila excess. Dipakai jalur auto-final maupun
     * listener approval. Idempoten: retur non-pending = no-op.
     */
    public function execute(int $returnId): PurchaseReturn
    {
        return DB::transaction(function () use ($returnId): PurchaseReturn {
            $purchaseReturn = PurchaseReturn::with('items')
                ->whereKey($returnId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($purchaseReturn->status !== PurchaseReturnStatus::Pending->value) {
                return $purchaseReturn;
            }

            $invoice = PurchaseInvoice::whereKey($purchaseReturn->purchase_invoice_id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($invoice->status, [PurchaseInvoiceStatus::ClosedByReturn, PurchaseInvoiceStatus::Cancelled], true)) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => 'Faktur sudah tertutup/dibatalkan dan tidak dapat diretur.',
                ]);
            }

            $invoiceItems = PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $taxes = collect($this->taxQuery->listForPurchase())->keyBy('id');
            $isInclusive = (bool) $purchaseReturn->is_tax_inclusive;

            $trackedNet = 0.0;
            $trackedFifo = 0.0;
            $untrackedByAccount = [];
            $taxTotal = 0.0;
            $inventoryByAccount = [];

            foreach ($purchaseReturn->items as $index => $item) {
                $invoiceItem = $invoiceItems->get($item->purchase_invoice_item_id);

                if (! $invoiceItem) {
                    throw ValidationException::withMessages([
                        "items.{$index}.purchase_invoice_item_id" => 'Baris faktur tidak ditemukan.',
                    ]);
                }

                $qty = (float) $item->qty;

                if ((float) $invoiceItem->qty_returned + $qty - (float) $invoiceItem->qty > 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => 'Qty retur melebihi sisa yang bisa diretur.',
                    ]);
                }

                $profile = $this->returnProfiles->execute((int) $item->product_variant_id);

                if (! $profile) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => 'Varian produk tidak ditemukan.',
                    ]);
                }

                $gross = $qty * (float) $item->unit_price;
                $calc = $this->taxCalculator->calculate(
                    $gross,
                    $this->taxDefFor($item->tax_id, (float) $item->tax_rate, $taxes),
                    $isInclusive
                );
                $net = $isInclusive ? $gross - $calc['total'] : $gross;
                $taxTotal += $calc['total'];

                if ($profile['is_tracked']) {
                    if (abs($qty - round($qty)) > 0.0001) {
                        throw ValidationException::withMessages([
                            "items.{$index}.qty" => 'Qty produk terpantau stok harus bilangan bulat.',
                        ]);
                    }

                    $stockVariantId = (int) ($item->stock_variant_id ?? $item->product_variant_id);

                    $issued = $this->issueReturnStock->execute(array_filter([
                        'warehouse_id' => (int) $purchaseReturn->warehouse_id,
                        'product_variant_id' => $stockVariantId,
                        'qty' => (int) round($qty),
                        'reference_type' => 'purchase_return',
                        'reference_id' => $purchaseReturn->id,
                        'source_transfer_id' => $purchaseReturn->return_transfer_id,
                    ], fn ($value) => $value !== null));

                    $accountId = $profile['inventory_account_id'] ?? $this->seedAccountId('accounting.coa.1301');
                    $inventoryByAccount[$accountId] = ($inventoryByAccount[$accountId] ?? 0.0) + $issued['total_cost'];
                    $trackedFifo += $issued['total_cost'];
                    $trackedNet += $net;
                } else {
                    $accountId = $profile['purchase_account_id'] ?? $this->seedAccountId('accounting.coa.5201');
                    $untrackedByAccount[$accountId] = ($untrackedByAccount[$accountId] ?? 0.0) + $net;
                }

                $invoiceItem->increment('qty_returned', $qty);
            }

            $grossTotal = $purchaseReturn->total;
            $paid = (float) $invoice->paid_amount;
            $returnedBefore = (float) $invoice->returned_amount;
            $outstandingBefore = max(0.0, (float) $invoice->total - $paid - $returnedBefore);

            $applied = min((float) $grossTotal, $outstandingBefore);
            $excess = (float) $grossTotal - $applied;

            $this->postJournal($purchaseReturn, $applied, $excess, $inventoryByAccount, $untrackedByAccount, $taxTotal);

            $returnedAfter = $returnedBefore + (float) $grossTotal;
            $invoice->update([
                'returned_amount' => $returnedAfter,
                'status' => $this->nextInvoiceStatus($invoice, $paid, $returnedAfter),
            ]);

            if ($excess > 0.005) {
                $this->createDebitMemo($purchaseReturn, $excess);
            }

            $purchaseReturn->update(['status' => PurchaseReturnStatus::Approved->value]);

            return $purchaseReturn->fresh('items');
        });
    }

    /**
     * @param  array<int, float>  $inventoryByAccount
     * @param  array<int, float>  $untrackedByAccount
     */
    private function postJournal(
        PurchaseReturn $purchaseReturn,
        float $applied,
        float $excess,
        array $inventoryByAccount,
        array $untrackedByAccount,
        float $taxTotal,
    ): void {
        $lines = [];

        if ($applied > 0.005) {
            $lines[] = [
                'account_id' => $this->payableAccounts->execute((int) $purchaseReturn->supplier_id)->id,
                'debit' => round($applied, 2),
                'credit' => 0,
            ];
        }

        if ($excess > 0.005) {
            $lines[] = [
                'account_id' => $this->seedAccountId('accounting.coa.1402'),
                'debit' => round($excess, 2),
                'credit' => 0,
            ];
        }

        foreach ($inventoryByAccount as $accountId => $amount) {
            if ($amount > 0.005) {
                $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => round($amount, 2)];
            }
        }

        foreach ($untrackedByAccount as $accountId => $amount) {
            if ($amount > 0.005) {
                $lines[] = ['account_id' => $accountId, 'debit' => 0, 'credit' => round($amount, 2)];
            }
        }

        if ($taxTotal > 0.005) {
            $lines[] = [
                'account_id' => $this->seedAccountId('accounting.coa.1404'),
                'debit' => 0,
                'credit' => round($taxTotal, 2),
            ];
        }

        // Selisih biaya FIFO vs harga DO + residu pembulatan masuk akun
        // penyesuaian agar jurnal selalu balance by construction.
        $sumDebit = array_sum(array_column($lines, 'debit'));
        $sumCredit = array_sum(array_column($lines, 'credit'));
        $adjustment = round($sumDebit - $sumCredit, 2);

        if (abs($adjustment) >= 0.005) {
            $lines[] = $adjustment > 0
                ? ['account_id' => $this->seedAccountId('accounting.coa.1398'), 'debit' => 0, 'credit' => $adjustment]
                : ['account_id' => $this->seedAccountId('accounting.coa.1398'), 'debit' => abs($adjustment), 'credit' => 0];
        }

        $this->recordJournal->execute([
            'branch_id' => (int) $purchaseReturn->branch_id,
            'journal_date' => $purchaseReturn->return_date->toDateString(),
            'reference_type' => 'purchase_return',
            'reference_id' => $purchaseReturn->id,
            'memo' => 'Retur pembelian '.$purchaseReturn->number,
            'lines' => $lines,
        ]);
    }

    private function nextInvoiceStatus(PurchaseInvoice $invoice, float $paid, float $returnedAfter): PurchaseInvoiceStatus
    {
        $outstandingAfter = (float) $invoice->total - $paid - $returnedAfter;

        if (round($outstandingAfter, 2) <= 0) {
            return $paid > 0.005 ? PurchaseInvoiceStatus::Paid : PurchaseInvoiceStatus::ClosedByReturn;
        }

        return ($paid > 0.005 || $returnedAfter > 0.005)
            ? PurchaseInvoiceStatus::PartiallyPaid
            : $invoice->status;
    }

    private function createDebitMemo(PurchaseReturn $purchaseReturn, float $excess): void
    {
        $datePart = now()->format('Ymd');

        $sequence = SupplierDebitMemo::whereDate('created_at', today())
            ->lockForUpdate()
            ->pluck('id')
            ->count() + 1;

        SupplierDebitMemo::create([
            'number' => sprintf('DM/%s/%03d', $datePart, $sequence),
            'branch_id' => $purchaseReturn->branch_id,
            'supplier_id' => $purchaseReturn->supplier_id,
            'purchase_return_id' => $purchaseReturn->id,
            'purchase_invoice_id' => $purchaseReturn->purchase_invoice_id,
            'status' => 'open',
            'total' => round($excess, 2),
            'remaining' => round($excess, 2),
        ]);
    }

    private function seedAccountId(string $seedKey): int
    {
        $account = $this->chartOfAccounts->findBySeedKey($seedKey);

        if (! $account) {
            throw new \LogicException("Akun [{$seedKey}] tidak ditemukan.");
        }

        return $account['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function taxDefFor(?int $taxId, float $storedRate, mixed $taxes): ?array
    {
        if ($taxId === null) {
            return null;
        }

        $def = $taxes->get($taxId);

        if ($def) {
            return $def;
        }

        return ['id' => $taxId, 'rate' => $storedRate];
    }
}
