<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetPurchaseVariants;

class StoreGoodsReceiptRequest extends FormRequest
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

        $supplierIds = collect(app(GetContacts::class)->execute('supplier', $branchIds))
            ->pluck('id')
            ->all();

        $activeVariantIds = collect(app(GetPurchaseVariants::class)->execute($branchIds))
            ->pluck('id')
            ->all();

        // Goods must be received into the HQ regular warehouse (administrative
        // flow holds even when supplier ships directly to a branch).
        $hqBranch = DB::table('branches')->where('is_headquarters', true)->first();
        $hqBranchId = $hqBranch ? (int) $hqBranch->id : null;

        $hqRegularWarehouseRule = function (string $attribute, mixed $value, Closure $fail) use ($hqBranchId) {
            $warehouse = DB::table('warehouses')->where('id', $value)->first();
            if (! $warehouse) {
                $fail('Gudang tidak ditemukan.');

                return;
            }
            if (! $warehouse->is_active) {
                $fail('Gudang tidak aktif.');

                return;
            }
            if ((int) $warehouse->branch_id !== (int) $hqBranchId) {
                $fail('Penerimaan barang harus masuk gudang milik HQ.');

                return;
            }
            if ($warehouse->warehouse_type !== 'regular') {
                $fail('Penerimaan barang harus masuk gudang bertipe Regular.');

                return;
            }
            if (! str_starts_with($warehouse->code, 'GD-')) {
                $fail('Penerimaan barang harus masuk gudang sistem Regular.');
            }
        };

        return [
            'branch_id' => ['required', 'integer', $branchAccessibleRule],
            'supplier_id' => ['required', 'integer', Rule::in($supplierIds)],
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'integer', $hqRegularWarehouseRule],
            'receipt_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['nullable', 'integer', 'exists:purchase_order_items,id'],
            // No `distinct` here: a multi-branch PO legitimately repeats the same
            // variant across PO lines for different destination branches, and the
            // GRN mirrors those PO lines 1:1.
            'items.*.product_variant_id' => ['required', 'integer', Rule::in($activeVariantIds)],
            'items.*.qty_received' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
