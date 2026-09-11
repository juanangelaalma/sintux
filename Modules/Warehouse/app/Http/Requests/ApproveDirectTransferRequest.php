<?php

namespace Modules\Warehouse\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Company\Application\CompanyAccess;

class ApproveDirectTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $tenantId = (string) session('active_tenant_id');

        /*
         * Hanya anggota cabang HQ yang boleh memproses
         * transfer menunggu persetujuan.
         */
        return CompanyAccess::can($user, $tenantId, 'warehouse.stock.transfer')
            && CompanyAccess::isActiveBranchHq($user, $tenantId);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'string', 'in:approve,reject'],
        ];
    }
}
