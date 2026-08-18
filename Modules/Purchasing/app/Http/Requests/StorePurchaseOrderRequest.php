<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;

class StorePurchaseOrderRequest extends FormRequest
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

        $activeVariantIds = collect(app(GetPurchaseVariants::class)->execute())
            ->pluck('id')
            ->all();

        $taxIds = collect(app(GetPurchaseTaxes::class)->execute())
            ->pluck('id')
            ->all();

        return [
            'branch_id' => ['required', 'integer', $branchAccessibleRule],
            'supplier_id' => ['required', 'integer', Rule::in($supplierIds)],
            'source_request_id' => ['nullable', 'integer', 'exists:purchase_requests,id'],
            'source_quote_id' => ['nullable', 'integer', 'exists:purchase_quotes,id'],
            'order_date' => ['required', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', Rule::in($activeVariantIds)],
            'items.*.qty_ordered' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'items.*.tax_id' => ['nullable', 'integer', Rule::in($taxIds)],
        ];
    }
}
