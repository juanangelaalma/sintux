<?php

namespace Modules\Warehouse\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Models\Warehouse;

class StoreStockRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $tenantId = (string) session('active_tenant_id');
        $user = $this->user();

        $branchIds = CompanyAccess::contextBranchIds($user, $tenantId);

        $branchAccessibleRule = function (string $attribute, mixed $value, Closure $fail) use ($branchIds) {
            $warehouse = Warehouse::find($value);
            if (! $warehouse || ! in_array((int) $warehouse->branch_id, $branchIds, true)) {
                $fail('Selected warehouse is not in your accessible branch context.');
            }
        };

        return [
            'requesting_warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                'different:destination_warehouse_id',
                $branchAccessibleRule,
            ],
            'destination_warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                'different:requesting_warehouse_id',
            ],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')->where('is_active', true),
            ],
            'items.*.qty_requested' => [
                'required',
                'numeric',
                'gt:0',
            ],
        ];
    }
}
