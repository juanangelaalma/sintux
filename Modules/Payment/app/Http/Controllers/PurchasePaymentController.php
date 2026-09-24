<?php

namespace Modules\Payment\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Approval\Application\GetTransactionApprovalStatus;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Payment\Application\Deposit\CreateDepositPayment;
use Modules\Payment\Application\Deposit\GetSupplierDeposits;
use Modules\Payment\Application\PurchasePayment\CreatePurchasePayment;
use Modules\Payment\Application\PurchasePayment\GetPaymentDetail;
use Modules\Payment\Http\Requests\StorePurchasePaymentRequest;
use Modules\Payment\Models\PaymentMethod;
use Modules\Purchasing\Application\PurchaseInvoice\GetPayableInvoices;
use Modules\Purchasing\Application\PurchaseTag\GetPurchaseTags;
use Modules\Purchasing\Application\SupplierMemo\ListSupplierDebitMemos;

class PurchasePaymentController extends Controller
{
    public function __construct(
        private readonly CreatePurchasePayment $createPayment,
        private readonly CreateDepositPayment $createDeposit,
        private readonly GetPaymentDetail $getDetail,
        private readonly GetPayableInvoices $payableInvoices,
        private readonly GetSupplierDeposits $supplierDeposits,
        private readonly ListSupplierDebitMemos $supplierMemos,
        private readonly ChartOfAccountQuery $chartOfAccounts,
        private readonly GetTransactionApprovalStatus $approvalStatus,
    ) {}

    public function new(): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);
        $hqBranchId = CompanyAccess::headquartersBranchId();
        $mode = request()->query('mode') === 'deposit' ? 'deposit' : 'invoice';

        $supplierId = (int) (request()->query('supplier_id') ?? 0);
        $createdFrom = (int) (request()->query('createdFrom') ?? 0);

        $prefillAllocation = null;

        if ($createdFrom > 0 && $mode === 'invoice') {
            $match = $this->payableInvoices->find($createdFrom, $accessibleBranchIds);

            if ($match) {
                $supplierId = (int) $match['supplier_id'];
                $prefillAllocation = [
                    'purchase_invoice_id' => (int) $match['id'],
                    'amount' => (float) $match['outstanding'],
                ];
            }
        }

        return Inertia::render('Payment/Purchase/create', [
            'mode' => $mode,
            'hqBranchId' => $hqBranchId,
            'branchIds' => $accessibleBranchIds,
            'suppliers' => app(GetContacts::class)->execute('supplier', $accessibleBranchIds),
            'paymentMethods' => PaymentMethod::where('is_active', true)->orderBy('name')->get(['id', 'name', 'code']),
            'accounts' => $this->chartOfAccounts->listChartOfAccounts(),
            'tags' => app(GetPurchaseTags::class)->execute(),
            'supplierId' => $supplierId,
            'payableInvoices' => $supplierId > 0
                ? $this->payableInvoices->execute($supplierId, $accessibleBranchIds)
                : [],
            'supplierDeposits' => $supplierId > 0
                ? $this->supplierDeposits->execute($supplierId)
                : [],
            'supplierMemos' => $supplierId > 0
                ? $this->supplierMemos->execute($supplierId, $accessibleBranchIds)
                : [],
            'prefillAllocation' => $prefillAllocation,
        ]);
    }

    public function store(StorePurchasePaymentRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);

        if (($validated['mode'] ?? 'invoice') === 'deposit') {
            $payment = $this->createDeposit->execute([
                'branch_id' => $validated['branch_id'],
                'supplier_id' => $validated['supplier_id'],
                'payment_method_id' => $validated['payment_method_id'] ?? null,
                'cash_account_id' => $validated['cash_account_id'],
                'amount' => (float) ($validated['amount'] ?? 0),
                'payment_date' => $validated['payment_date'],
                'currency_code' => $validated['currency_code'] ?? 'IDR',
                'memo' => $validated['memo'] ?? null,
            ], $user->id);
        } else {
            $payment = $this->createPayment->execute(
                $validated,
                $user->id,
                $user->name,
                $accessibleBranchIds
            );
        }

        return redirect()->route('purchase-payments.show', $payment->id)
            ->with('success', 'Pembayaran berhasil disimpan.');
    }

    public function show(int $id): Response
    {
        $user = request()->user();
        $tenantId = (string) session('active_tenant_id');
        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);
        $detail = $this->getDetail->execute($id);

        return Inertia::render('Payment/Purchase/show', [
            'payment' => $detail,
            'approval' => $this->approvalStatus->execute('purchase_payment', $id, $user?->id),
        ]);
    }
}
