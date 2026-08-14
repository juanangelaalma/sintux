<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('barcode')->nullable();
            $table->foreignId('category_id')->constrained('product_categories');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('product_type')->default('single'); // 'single' | 'bundle'

            // Purchase settings
            $table->boolean('is_purchased')->default(true);
            $table->decimal('purchase_price', 15, 4)->default(0);
            $table->foreignId('purchase_account_id')->nullable(); // CoA (future)
            $table->foreignId('purchase_tax_id')->nullable();

            // Sales settings
            $table->boolean('is_sold')->default(true);
            $table->decimal('selling_price', 15, 4)->default(0);
            $table->foreignId('sales_account_id')->nullable(); // CoA (future)
            $table->foreignId('sales_tax_id')->nullable();

            // Inventory monitoring
            $table->boolean('is_inventory_tracked')->default(true);
            $table->decimal('min_stock', 15, 4)->default(0);
            $table->foreignId('inventory_account_id')->nullable(); // CoA (future)

            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX products_code_unique ON products (code) WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
