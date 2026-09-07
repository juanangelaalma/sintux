<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\GetPurchaseTaxes;
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

        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);

        $branchAccessibleRule = function (string $attribute, mixed $value, Closure $fail) use ($branchIds) {
            if (! in_array((int) $value, $branchIds, true)) {
                $fail('Branch tidak berada dalam cakupan akses Anda.');
            }
        };

        $supplierIds = collect(app(GetContacts::class)->execute('supplier', $branchIds))
            ->pluck('id')
            ->all();

        $activeVariantIds = collect(app(GetPurchaseVariants::class)->execute($branchIds))
            ->pluck('id')
            ->all();

        $taxIds = collect(app(GetPurchaseTaxes::class)->execute())
            ->pluck('id')
            ->all();

        return [
            'branch_id' => ['required', 'integer', $branchAccessibleRule],
            'supplier_id' => ['required', 'integer', Rule::in($supplierIds)],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'invoice_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', Rule::in($activeVariantIds)],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.tax_id' => ['nullable', 'integer', Rule::in($taxIds)],
        ];
    }
}
