<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Company\Application\CompanyAccess;

class StoreJoinPurchaseInvoiceRequest extends FormRequest
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

        return [
            'branch_id' => ['required', 'integer', $branchAccessibleRule],
            'join_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_invoice_id' => ['required', 'integer', 'exists:purchase_invoices,id'],
            'items.*.supplier_id' => ['required', 'integer', 'exists:contacts,id'],
            'items.*.invoice_number' => ['required', 'string'],
            'items.*.supplier_name' => ['required', 'string'],
            'items.*.invoice_total' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
