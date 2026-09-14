<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Qty stok disimpan sebagai integer (satuan pcs), bukan decimal.
     * unit_cost tetap decimal karena itu nilai uang.
     *
     * @var array<string, list<string>>
     */
    private array $columns = [
        'stock_request_items' => ['qty_requested', 'qty_approved'],
        'stock_transfer_items' => ['qty', 'qty_shipped', 'qty_received'],
        'stock_layers' => ['qty_remaining'],
        'stock_transfer_item_layers' => ['qty_taken'],
        'stock_movements' => ['qty'],
        'stock_adjustment_items' => ['qty'],
        'stock_transfer_discrepancies' => ['shipped_qty', 'received_qty', 'difference_qty'],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE integer USING \"{$column}\"::integer"
                );
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement(
                    "ALTER TABLE \"{$table}\" ALTER COLUMN \"{$column}\" TYPE decimal(15,4) USING \"{$column}\"::decimal(15,4)"
                );
            }
        }
    }
};
