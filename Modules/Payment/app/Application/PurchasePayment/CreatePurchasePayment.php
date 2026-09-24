<?php

namespace Modules\Payment\Application\PurchasePayment;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Company\Application\CompanyAccess;
use Modules\Payment\Application\Finalize\FinalizePurchasePayment;
use Modules\Payment\Models\PurchasePayment;
use Modules\Purchasing\Application\PurchaseInvoice\GetPayableInvoices;
use Modules\Purchasing\Application\SupplierMemo\ListSupplierDebitMemos;

/**
 * Create pembayaran pembelian: alokasi ke faktur, withholding
 * (persen/nominal + akun), apply uang muka, apply kredit Debit Memo,
 * hitung cash_out, simpan pending, evaluasi approval (auto-final bila
 * tanpa rule).
 *
 * Modul lain hanya via Application API: GetPayableInvoices + ListSupplierDebitMemos
 * (Purchasing), ChartOfAccountQuery + RecordJournal (Accounting, via Finalize).
 */
class CreatePurchasePayment
{
    public function __construct(
        private readonly GetPayableInvoices $payableInvoices,
        private readonly ListSupplierDebitMemos $debitMemos,
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly ApprovalEngine $approvalEngine,
        private readonly FinalizePurchasePayment $finalizePurchasePayment,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds
     */
    public function execute(array $data, ?int $userId = null, ?string $userName = null, array $branchIds = []): PurchasePayment
    {
        return DB::transaction(function () use ($data, $userId, $userName, $branchIds): PurchasePayment {
            $hqBranchId = CompanyAccess::headquartersBranchId();
            $branchId = (int) ($data['branch_id'] ?? $hqBranchId ?? 0);

            if ($hqBranchId !== null && $branchId !== $hqBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Pembayaran hanya dapat dibuat untuk Head Office.',
                ]);
            }

            $supplierId = (int) ($data['supplier_id'] ?? 0);
            $allocations = $this->normalizeAllocations($data['allocations'] ?? []);

            if ($allocations === []) {
                throw ValidationException::withMessages([
                    'allocations' => 'Pembayaran minimal memiliki 1 alokasi faktur.',
                ]);
            }

            // Validasi alokasi terhadap outstanding faktur milik supplier.
            $payable = collect($this->payableInvoices->execute($supplierId, $branchIds))->keyBy('id');
            $gross = 0.0;
            $currencyCode = (string) ($data['currency_code'] ?? 'IDR');

            foreach ($allocations as $allocation) {
                $invoice = $payable->get($allocation['purchase_invoice_id']);

                if (! $invoice) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Faktur tidak tersedia untuk dibayar (supplier/cabang/status/outstanding).',
                    ]);
                }

                if ((string) $invoice['currency_code'] !== $currencyCode) {
                    throw ValidationException::withMessages([
                        'currency_code' => 'Semua faktur harus satu mata uang dengan pembayaran.',
                    ]);
                }

                if ($allocation['amount'] - (float) $invoice['outstanding'] > 0.0001) {
                    throw ValidationException::withMessages([
                        'allocations' => 'Alokasi melebihi outstanding faktur '.$invoice['number'].'.',
                    ]);
                }

                $gross += $allocation['amount'];
            }

            $gross = round($gross, 4);

            // Withholding (persen/nominal + akun).
            $withholdingTotal = 0.0;
            $withholdings = [];

            foreach ($data['withholdings'] ?? [] as $index => $withholding) {
                $accountId = (int) ($withholding['account_id'] ?? 0);

                if (! $this->chartOfAccounts->isEligible($accountId)) {
                    throw ValidationException::withMessages([
                        "withholdings.{$index}.account_id" => 'Akun pemotongan tidak valid.',
                    ]);
                }

                $type = (string) ($withholding['type'] ?? 'nominal');
                $value = (float) ($withholding['value'] ?? 0);

                if ($type === 'percent') {
                    if ($value < 0 || $value > 100) {
                        throw ValidationException::withMessages([
                            "withholdings.{$index}.value" => 'Persen pemotongan harus 0–100.',
                        ]);
                    }
                    $amount = round($gross * $value / 100, 4);
                } else {
                    if ($value <= 0) {
                        throw ValidationException::withMessages([
                            "withholdings.{$index}.value" => 'Nominal pemotongan harus lebih dari 0.',
                        ]);
                    }
                    $amount = round($value, 4);
                }

                if ($amount - $gross > 0.0001) {
                    throw ValidationException::withMessages([
                        "withholdings.{$index}.value" => 'Pemotongan melebihi total alokasi.',
                    ]);
                }

                $withholdingTotal += $amount;
                $withholdings[] = compact('accountId', 'type', 'value', 'amount');
            }

            $withholdingTotal = round($withholdingTotal, 4);

            // Apply uang muka.
            $depositApplied = 0.0;
            $depositUses = [];

            foreach ($data['deposit_uses'] ?? [] as $depositUse) {
                $depositPayment = PurchasePayment::whereKey((int) ($depositUse['payment_id'] ?? 0))
                    ->where('supplier_id', $supplierId)
                    ->where('mode', 'deposit')
                    ->lockForUpdate()
                    ->first();

                if (! $depositPayment) {
                    throw ValidationException::withMessages([
                        'deposit_uses' => 'Uang muka tidak ditemukan untuk supplier ini.',
                    ]);
                }

                $amount = (float) ($depositUse['amount'] ?? 0);

                if ($amount - (float) $depositPayment->deposit_remaining > 0.0001) {
                    throw ValidationException::withMessages([
                        'deposit_uses' => 'Pemakaian uang muka melebihi sisa.',
                    ]);
                }

                $depositApplied += $amount;
                $depositUses[] = ['payment' => $depositPayment, 'amount' => $amount];
            }

            $depositApplied = round($depositApplied, 4);

            // Apply kredit Debit Memo supplier.
            $memoApplied = 0.0;
            $memoUses = [];

            foreach ($data['memo_uses'] ?? [] as $memoUse) {
                $memo = collect($this->debitMemos->execute($supplierId, $branchIds))
                    ->firstWhere('id', (int) ($memoUse['memo_id'] ?? 0));

                if (! $memo) {
                    throw ValidationException::withMessages([
                        'memo_uses' => 'Debit Memo tidak ditemukan atau sudah habis.',
                    ]);
                }

                $amount = (float) ($memoUse['amount'] ?? 0);

                if ($amount - (float) $memo['remaining'] > 0.0001) {
                    throw ValidationException::withMessages([
                        'memo_uses' => 'Pemakaian Debit Memo melebihi sisa kredit.',
                    ]);
                }

                $memoApplied += $amount;
                $memoUses[] = ['memo_id' => (int) $memo['id'], 'amount' => $amount];
            }

            $memoApplied = round($memoApplied, 4);

            $cashOut = round($gross - $withholdingTotal - $depositApplied - $memoApplied, 4);

            if ($cashOut < -0.0001) {
                throw ValidationException::withMessages([
                    'cash_out' => 'Total pemotongan + kredit melebihi nilai alokasi.',
                ]);
            }

            $cashOut = max(0.0, $cashOut);

            $payment = PurchasePayment::create([
                'number' => $this->nextNumber(),
                'branch_id' => $branchId,
                'supplier_id' => $supplierId,
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'mode' => 'invoice',
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'currency_code' => $currencyCode,
                'cash_account_id' => (int) ($data['cash_account_id'] ?? 0),
                'gross_amount' => $gross,
                'withholding_amount' => $withholdingTotal,
                'deposit_applied' => $depositApplied,
                'memo_applied' => $memoApplied,
                'cash_out' => $cashOut,
                'deposit_total' => 0,
                'deposit_remaining' => 0,
                'status' => 'pending',
                'memo' => $data['memo'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($allocations as $allocation) {
                $payment->allocations()->create($allocation);
            }

            foreach ($withholdings as $withholding) {
                $payment->withholdings()->create([
                    'account_id' => $withholding['accountId'],
                    'type' => $withholding['type'],
                    'value' => $withholding['value'],
                    'amount' => $withholding['amount'],
                ]);
            }

            foreach ($depositUses as $use) {
                $payment->depositUses()->create([
                    'source_payment_id' => $use['payment']->id,
                    'amount' => $use['amount'],
                ]);
            }

            foreach ($memoUses as $use) {
                $payment->memoUses()->create([
                    'supplier_debit_memo_id' => $use['memo_id'],
                    'amount' => $use['amount'],
                ]);
            }

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'purchase_payment',
                'transaction_id' => $payment->id,
                'document_number' => $payment->number,
                'created_by' => $userId ?? 0,
                'created_by_name' => $userName,
                'branch_id' => $branchId,
                'total' => $cashOut,
                'currency_code' => $currencyCode,
            ]);

            if (! $mapping) {
                $this->finalizePurchasePayment->execute($payment->id);
                $payment->refresh();
            }

            return $payment->load('allocations');
        });
    }

    /**
     * @return list<array{purchase_invoice_id: int, amount: float}>
     */
    private function normalizeAllocations(mixed $allocations): array
    {
        $out = [];

        foreach ((array) $allocations as $allocation) {
            $amount = (float) ($allocation['amount'] ?? 0);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'allocations' => 'Nominal alokasi harus lebih dari 0.',
                ]);
            }

            $out[] = [
                'purchase_invoice_id' => (int) ($allocation['purchase_invoice_id'] ?? 0),
                'amount' => round($amount, 4),
            ];
        }

        return $out;
    }

    private function nextNumber(): string
    {
        DB::statement("SELECT pg_advisory_xact_lock(hashtext('purchase_payment_number'))");

        $date = now()->format('Ymd');
        $count = PurchasePayment::whereDate('created_at', today())->count() + 1;

        return sprintf('PBL/%s/%03d', $date, $count);
    }
}
