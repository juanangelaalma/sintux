<?php

namespace Modules\Warehouse\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Models\StockRequest;

class ApproveStockRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        $tenantId = (string) session('active_tenant_id');

        if (! CompanyAccess::can($user, $tenantId, 'warehouse.stock.request.approve')) {
            return false;
        }

        $param = $this->route('stock_request');
        $stockRequestId = is_object($param) ? (int) $param->id : (int) $param;

        $stockRequest = StockRequest::with('destinationWarehouse')->find($stockRequestId);

        if (! $stockRequest || ! $stockRequest->destinationWarehouse) {
            return false;
        }

        $accessibleBranchIds = CompanyAccess::contextBranchIds($user, $tenantId);

        return in_array((int) $stockRequest->destinationWarehouse->branch_id, $accessibleBranchIds, true);
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'items.*.qty_approved' => ['required', 'numeric', 'min:0'],
        ];
    }
}
