<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;

class StorePurchaseReturnRequest extends FormRequest
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

            if ($hqBranchId !== null && (int) $value !== $hqBranchId) {
                $fail('Retur pembelian hanya dapat dibuat untuk Head Office.');
            }
        };

        $supplierIds = collect(app(GetContacts::class)->execute('supplier', $branchIds))
            ->pluck('id')
            ->all();

        return [
            'branch_id' => ['required', 'integer', $branchHoRule],
            'supplier_id' => ['required', 'integer', Rule::in($supplierIds)],
            'purchase_invoice_id' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'return_date' => ['required', 'date', 'before_or_equal:today'],
            'message' => ['nullable', 'string', 'max:1000'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:purchase_tags,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_invoice_item_id' => ['required', 'integer', 'exists:purchase_invoice_items,id'],
            'items.*.qty' => ['required', 'numeric', 'gt:0'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
