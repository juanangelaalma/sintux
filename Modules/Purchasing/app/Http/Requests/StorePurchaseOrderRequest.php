<?php

namespace Modules\Purchasing\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Company\Application\CompanyAccess;
use Modules\Contact\Application\GetContacts;

class StorePurchaseOrderRequest extends FormRequest
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

        $hqBranch = DB::table('branches')->where('is_headquarters', true)->first();
        $hqBranchId = $hqBranch ? (int) $hqBranch->id : null;

        $branchIsHqRule = function (string $attribute, mixed $value, Closure $fail) use ($hqBranchId) {
            if (! $hqBranchId || (int) $value !== $hqBranchId) {
                $fail('Purchase Order hanya dapat dibuat untuk cabang HQ.');
            }
        };

        $supplierIds = collect(app(GetContacts::class)->execute('supplier', $branchIds))
            ->pluck('id')
            ->all();

        $taxIds = collect(app(GetPurchaseTaxes::class)->execute())
            ->pluck('id')
            ->all();

        $hqRegularWarehouseRule = function (string $attribute, mixed $value, Closure $fail) use ($hqBranchId) {
            $warehouse = DB::table('warehouses')->where('id', $value)->first();
            if (! $warehouse) {
                $fail('Gudang tidak ditemukan.');

                return;
            }
            if ((int) $warehouse->branch_id !== (int) $hqBranchId) {
                $fail('Gudang PO harus milik HQ.');

                return;
            }
            if ($warehouse->warehouse_type !== 'regular') {
                $fail('Gudang PO harus bertipe Regular (GD-HQ-REG).');

                return;
            }
            if (! str_starts_with($warehouse->code, 'GD-')) {
                $fail('Gudang PO harus gudang sistem Regular.');
            }
        };

        return [
            'branch_id' => ['required', 'integer', $branchIsHqRule],
            'supplier_id' => ['required', 'integer', Rule::in($supplierIds)],
            'supplier_email' => ['nullable', 'email', 'max:255'],
            'supplier_reference' => ['nullable', 'string', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:500'],
            'warehouse_id' => ['required', 'integer', $hqRegularWarehouseRule],
            'payment_term' => ['nullable', 'string', 'max:50'],
            'order_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'is_tax_inclusive' => ['boolean'],
            'note' => ['nullable', 'string', 'max:1000'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('purchase_tags', 'id')],
            'items' => ['required', 'array', 'min:1'],
            'items.*.destination_branch_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($branchIds) {
                if (! in_array((int) $value, $branchIds, true)) {
                    $fail('Cabang tujuan tidak berada dalam cakupan akses Anda.');
                }
            }],
            'items.*.destination_warehouse_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                // Extract index from attribute like items.0.destination_warehouse_id
                $parts = explode('.', $attribute);
                $index = $parts[1] ?? null;
                $destBranchId = $this->input("items.{$index}.destination_branch_id");

                $warehouse = DB::table('warehouses')->where('id', $value)->first();
                if (! $warehouse) {
                    $fail('Gudang tujuan tidak ditemukan.');

                    return;
                }
                if ($warehouse->warehouse_type !== 'regular') {
                    $fail('Gudang tujuan harus bertipe Regular.');

                    return;
                }
                if (! str_starts_with($warehouse->code, 'GD-')) {
                    $fail('Gudang tujuan harus gudang sistem Regular.');

                    return;
                }
                if ($destBranchId && (int) $warehouse->branch_id !== (int) $destBranchId) {
                    $fail('Gudang tujuan harus milik cabang tujuan.');
                }
            }],
            'items.*.destination_expected_date' => ['required', 'date', 'after_or_equal:order_date'],
            'items.*.product_variant_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                // Requirement: varian tidak wajib milik cabang tujuan. HO/HQ mem-PO
                // varian master (mis. milik HQ) untuk dialokasikan ke cabang mana pun;
                // mapping ke produk masing-masing cabang dilakukan saat penerimaan
                // Transfer Stok. Cukup pastikan varian dan produknya ada dan aktif.
                $variant = DB::table('product_variants')->where('id', $value)->first();
                if (! $variant) {
                    $fail('Varian produk tidak ditemukan.');

                    return;
                }
                if (! $variant->is_active) {
                    $fail('Varian produk tidak aktif.');

                    return;
                }
                $product = DB::table('products')->where('id', $variant->product_id)->first();
                if (! $product || ! $product->is_active) {
                    $fail('Produk tidak aktif.');
                }
            }],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.qty_ordered' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gte:0'],
            'items.*.tax_id' => ['nullable', 'integer', Rule::in($taxIds)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $items = $this->input('items', []);
            if (! is_array($items) || $items === []) {
                return;
            }
            $branchDates = [];
            foreach ($items as $idx => $item) {
                $branchId = $item['destination_branch_id'] ?? null;
                $date = $item['destination_expected_date'] ?? null;
                if ($branchId === null || $date === null) {
                    continue;
                }
                $branchKey = (string) $branchId;
                if (! isset($branchDates[$branchKey])) {
                    $branchDates[$branchKey] = $date;
                } elseif ($branchDates[$branchKey] !== $date) {
                    $validator->errors()->add("items.{$idx}.destination_expected_date", 'Tanggal kirim untuk cabang yang sama harus konsisten.');
                }
            }
        });
    }
}
