<?php

namespace Modules\Sales\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\TaxQuery;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetSaleVariants;

class StoreSalesInvoiceRequest extends FormRequest
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

        $customerIds = collect(app(GetContacts::class)->execute('customer', $branchIds))
            ->pluck('id')
            ->all();

        $employeeIds = collect(app(GetContacts::class)->execute('employee', $branchIds))
            ->pluck('id')
            ->all();

        $activeVariantIds = collect(app(GetSaleVariants::class)->execute($branchIds))
            ->pluck('id')
            ->all();

        $taxIds = collect(app(TaxQuery::class)->listForSale())
            ->pluck('id')
            ->all();

        return [
            'customer_id' => ['required', 'integer', Rule::in($customerIds)],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'transaction_type' => ['required', 'string', Rule::in(['regular', 'consignment'])],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'salesperson_id' => ['required', 'integer', Rule::in($employeeIds)],
            'payment_term' => ['nullable', 'string', 'max:50'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'is_tax_inclusive' => ['sometimes', 'boolean'],
            'invoice_discount_type' => ['nullable', 'string', Rule::in(['percent', 'nominal'])],
            'invoice_discount_value' => ['nullable', 'numeric', 'gte:0', 'required_with:invoice_discount_type'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', Rule::in($activeVariantIds)],
            'items.*.qty' => ['required', 'integer', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.discount_type' => ['nullable', 'string', Rule::in(['percent', 'nominal'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'gte:0', 'required_with:items.*.discount_type'],
            'items.*.tax_id' => ['nullable', 'integer', Rule::in($taxIds)],
        ];
    }
}
