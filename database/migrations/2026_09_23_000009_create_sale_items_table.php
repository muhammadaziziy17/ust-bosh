<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->string('product_name_snapshot');
            $table->string('sku_snapshot', 64);
            $table->string('size_snapshot', 20);
            $table->string('color_snapshot', 50)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->unique(['shop_id', 'sale_id', 'product_variant_id']);
            $table->index(['shop_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
