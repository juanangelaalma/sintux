<?php

namespace Modules\Warehouse\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Company\Application\CompanyAccess;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockTransfer;

class StoreReturnStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $tenantId = (string) session('active_tenant_id');
        $branchIds = CompanyAccess::contextBranchIds($this->user(), $tenantId);

        /*
         * Retur transfer hanya sah bila dibuat dari transfer yang sudah
         * diterima, dan gudang sumber RTRF (tujuan transfer outbound)
         * harus milik cabang yang bisa diakses pengguna. Ini mencegah
         * pengguna branch membuat RTRF dari gudang branch lain.
         */
        $originScopeRule = function (string $attribute, mixed $value, Closure $fail) use ($branchIds) {
            /** @var StockTransfer|null $origin */
            $origin = StockTransfer::with(['fromWarehouse.branch', 'toWarehouse.branch'])->find($value);

            if (! $origin) {
                $fail('Transfer asal tidak ditemukan.');

                return;
            }

            if ((string) $origin->status !== StockTransferStatus::Received->value) {
                $fail('Retur hanya bisa dibuat dari transfer yang sudah diterima.');

                return;
            }

            if (! $origin->fromWarehouse?->branch?->is_headquarters) {
                $fail('Retur transfer hanya bisa dikembalikan ke gudang Head Office.');

                return;
            }

            if ((bool) $origin->toWarehouse?->branch?->is_headquarters) {
                $fail('Retur transfer hanya bisa dibuat untuk barang yang berada di cabang.');

                return;
            }

            $returnSourceBranchId = (int) ($origin->toWarehouse?->branch_id ?? 0);

            if (! in_array($returnSourceBranchId, $branchIds, true)) {
                $fail('Gudang asal retur tidak berada dalam cabang yang bisa diakses.');
            }
        };

        $originId = (int) $this->input('origin_transfer_id');

        return [
            'origin_transfer_id' => [
                'required',
                'integer',
                'exists:stock_transfers,id',
                $originScopeRule,
            ],
            'items' => ['required', 'array', 'min:1'],
            'items.*.stock_transfer_item_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('stock_transfer_items', 'id')
                    ->where('stock_transfer_id', $originId),
            ],
            'items.*.qty' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.stock_transfer_item_id.exists' => 'Barang tidak ada di transfer asal.',
        ];
    }
}
