<?php

namespace Modules\Warehouse\Application\StockAdjustment;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockAdjustment;

class CreateStockAdjustment
{
    /**
     * @param  array{
     *     warehouse_id: int,
     *     type: string,
     *     note?: string|null,
     *     items: list<array{
     *         product_variant_id: int,
     *         qty: float|int,
     *         unit_cost?: float|int|null,
     *         note?: string|null
     *     }>
     * }  $data
     */
    public function execute(array $data, int $userId): StockAdjustment
    {
        if (! in_array($data['type'], ['in', 'out'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Tipe penyesuaian stok harus "in" atau "out".',
            ]);
        }

        if (empty($data['items'])) {
            throw ValidationException::withMessages([
                'items' => 'Penyesuaian stok harus memiliki minimal 1 item.',
            ]);
        }

        return DB::transaction(function () use ($data, $userId) {
            $prefix = strtoupper($data['type']) === 'IN' ? 'ADJ-IN' : 'ADJ-OUT';
            $date = date('Ymd');
            $countToday = StockAdjustment::where('adjustment_number', 'like', "{$prefix}-{$date}-%")->count() + 1;
            $number = sprintf('%s-%s-%04d', $prefix, $date, $countToday);

            $adjustment = StockAdjustment::create([
                'warehouse_id' => $data['warehouse_id'],
                'adjustment_number' => $number,
                'type' => strtolower($data['type']),
                'status' => 'draft',
                'note' => $data['note'] ?? null,
                'adjusted_by' => $userId,
            ]);

            foreach ($data['items'] as $item) {
                $adjustment->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'qty' => (float) $item['qty'],
                    'unit_cost' => isset($item['unit_cost']) ? (float) $item['unit_cost'] : null,
                    'note' => $item['note'] ?? null,
                ]);
            }

            return $adjustment->load(['warehouse', 'items.productVariant.product', 'adjustedBy']);
        });
    }
}
