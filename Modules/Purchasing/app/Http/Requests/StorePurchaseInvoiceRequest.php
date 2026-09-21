<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\TaxQuery;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;

class StorePurchaseInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) session('active_tenant_id');
        $user = $this->user();

        $branchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);
        $hqBranchId = CompanyAccess::headquartersBranchId();

        $branchHoRule = function (string $attribute, mixed $value, Closure $fail) use ($branchIds, $hqBranchId) {
            if (! in_array((int) $value, $branchIds, true)) {
                $fail('Branch tidak berada dalam cakupan akses Anda.');

                return;
            }
            // Pembebanan hutang PO selalu di HO.
            if ($hqBranchId !== null && (int) $value !== $hqBranchId) {
                $fail('Faktur pembelian hanya dapat dibuat untuk Head Office.');
            }
        };

        $supplierIds = collect(app(GetContacts::class)->execute('supplier', $branchIds))
            ->pluck('id')
            ->all();

        $activeVariantIds = collect(app(GetPurchaseVariants::class)->execute($branchIds))
            ->pluck('id')
            ->all();

        $taxIds = collect(app(TaxQuery::class)->listForPurchase())
            ->pluck('id')
            ->all();

        return [
            'branch_id' => ['required', 'integer', $branchHoRule],
            'supplier_id' => ['required', 'integer', Rule::in($supplierIds)],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'goods_receipt_id' => ['nullable', 'integer', 'exists:goods_receipts,id'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'tax_invoice_no' => ['nullable', 'string', 'max:100'],
            'is_tax_inclusive' => ['sometimes', 'boolean'],
            'invoice_date' => ['required', 'date'],
            // Jatuh tempo boleh lampau: faktur supplier yang sudah jatuh
            // tempo tetap harus bisa dicatat.
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.goods_receipt_item_id' => ['nullable', 'integer', 'exists:goods_receipt_items,id'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::in($activeVariantIds)],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.tax_id' => ['nullable', 'integer', Rule::in($taxIds)],
        ];
    }
}
