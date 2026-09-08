<?php

namespace Modules\Contact\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Company\Application\CompanyAccess;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = auth()->user();
        $tenantId = (string) tenant('id');

        return $user !== null && CompanyAccess::hasMembership($user, $tenantId);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer', $this->branchAccessibleRule()],
            'name' => ['required', 'string', 'max:255'],
            'registered_at' => ['required', 'date'],
            'tier_relation' => ['nullable', 'string', 'in:A,B,C,D,E,R'],
            'identity_type' => ['nullable', 'string', 'in:KTP,SIM,Pasport'],
            'identity_number' => ['nullable', 'string', 'max:50'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'mobile_phone' => ['nullable', 'string', 'max:50'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'fax' => ['nullable', 'string', 'max:50'],
            'npwp' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_branch' => ['nullable', 'string', 'max:255'],
            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'shipping_same_as_billing' => ['sometimes', 'boolean'],
            'billing_address' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
            ...$this->addressRules('billing_address'),
            ...$this->addressRules('shipping_address'),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function addressRules(string $prefix): array
    {
        return [
            "{$prefix}.detail" => ['nullable', 'string', 'max:255'],
            "{$prefix}.rt" => ['nullable', 'string', 'max:10'],
            "{$prefix}.rw" => ['nullable', 'string', 'max:10'],
            "{$prefix}.kelurahan" => ['nullable', 'string', 'max:255'],
            "{$prefix}.kecamatan" => ['nullable', 'string', 'max:255'],
            "{$prefix}.kabupaten" => ['nullable', 'string', 'max:255'],
            "{$prefix}.provinsi" => ['nullable', 'string', 'max:255'],
            "{$prefix}.latitude" => ['nullable', 'numeric', 'between:-90,90'],
            "{$prefix}.longitude" => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Closure that rejects branch ids outside the user's active context branch scope.
     */
    private function branchAccessibleRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $tenantId = (string) tenant('id');
            $user = auth()->user();

            $branchIds = CompanyAccess::contextBranchIds($user, $tenantId);

            if (! in_array((int) $value, $branchIds, true)) {
                $fail('The selected branch is not accessible.');
            }
        };
    }
}
