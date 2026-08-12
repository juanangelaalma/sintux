<?php

namespace Modules\Warehouse\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Access\CompanyAccess;
use Modules\Warehouse\Models\Warehouse;

class StoreStockAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $tenantId = (string) session('active_tenant_id');
        $user = $this->user();

        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId)
            ?? CompanyAccess::accessibleBranchIds($user, $tenantId);

        $branchAccessibleRule = function (string $attribute, mixed $value, Closure $fail) use ($branchIds) {
            $warehouse = Warehouse::find($value);
            if (! $warehouse || ! in_array((int) $warehouse->branch_id, $branchIds, true)) {
                $fail('Selected warehouse is not in your accessible branch context.');
            }
        };

        return [
            'warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                $branchAccessibleRule,
            ],
            'type' => ['required', 'string', Rule::in(['in', 'out'])],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')->where('is_active', true),
            ],
            'items.*.qty' => [
                'required',
                'numeric',
                'gt:0',
            ],
            'items.*.unit_cost' => [
                'nullable',
                'numeric',
                'gte:0',
            ],
            'items.*.note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
