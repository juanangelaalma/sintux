<?php

namespace Modules\Warehouse\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Models\Warehouse;

class StoreDirectTransferRequest extends FormRequest
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

        /*
         * Gudang asal wajib berada dalam branch yang accessible user,
         * sehingga user non-HO hanya bisa membuat transfer dari gudangnya sendiri
         * (yang otomatis berstatus pending_approval).
         */
        $sourceAccessibleRule = function (string $attribute, mixed $value, Closure $fail) use ($branchIds) {
            $warehouse = Warehouse::find($value);
            if (! $warehouse || ! in_array((int) $warehouse->branch_id, $branchIds, true)) {
                $fail('Selected warehouse is not in your accessible branch context.');
            }
        };

        return [
            'from_warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                'different:to_warehouse_id',
                $sourceAccessibleRule,
            ],
            'to_warehouse_id' => [
                'required',
                'integer',
                'exists:warehouses,id',
                'different:from_warehouse_id',
            ],
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
        ];
    }
}
