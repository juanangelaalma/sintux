<?php

namespace Modules\Warehouse\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Application\CompanyAccess;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $warehouseId = $this->route('warehouse');

        return [
            'branch_id' => ['required', 'integer', $this->branchAccessibleRule()],
            'code' => ['required', 'string', 'max:50', Rule::unique('warehouses', 'code')->ignore($warehouseId)],
            'name' => ['required', 'string', 'max:255'],
            'warehouse_type' => ['required', 'string', 'in:regular,retail,consignment'],
            'address' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    private function branchAccessibleRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tenantId = (string) tenant('id');
            $branchIds = CompanyAccess::contextBranchIds(auth()->user(), $tenantId)
                ?? CompanyAccess::accessibleBranchIds(auth()->user(), $tenantId);

            if (! in_array((int) $value, $branchIds, true)) {
                $fail('The selected branch is not accessible.');
            }
        };
    }
}
