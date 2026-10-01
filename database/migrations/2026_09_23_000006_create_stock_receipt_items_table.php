<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('stock_receipt_id')->constrained('stock_receipts')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'stock_receipt_id', 'product_variant_id'], 'stock_receipt_items_shop_variant_unique');
            $table->index(['shop_id', 'product_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipt_items');
    }
};
