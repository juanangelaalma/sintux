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
            $table->foreignId('category_id')->constrained('product_categories');
            $table->foreignId('brand_id')->nullable()->constrained('brands');
            $table->foreignId('uom_id')->constrained('uoms');
            $table->text('description')->nullable();
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
