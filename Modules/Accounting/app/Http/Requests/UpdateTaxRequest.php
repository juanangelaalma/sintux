<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Models\Tax;

class UpdateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ChartOfAccountQuery $accounts): array
    {
        $taxId = (int) $this->route('tax');

        $rules = (new StoreTaxRequest)->rules($accounts);
        $rules['name'] = ['sometimes', 'required', 'string', 'max:100'];
        $rules['code'] = ['sometimes', 'required', 'string', 'max:30', Rule::unique('taxes', 'code')->ignore($taxId)];
        $rules['type'] = ['sometimes', Rule::in([Tax::TYPE_SINGLE, Tax::TYPE_GROUP])];
        $rules['rate'] = ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'];
        $rules['members'] = ['sometimes', 'required_if:type,group', 'array', 'min:1'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return (new StoreTaxRequest)->messages();
    }
}
