<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('size', 20);
            $table->string('color', 50)->nullable();
            $table->string('barcode', 64)->nullable();
            $table->unsignedInteger('stock_on_hand')->default(0);
            $table->unsignedInteger('reorder_level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['shop_id', 'product_id', 'size', 'color']);
            $table->unique(['shop_id', 'barcode']);
            $table->index(['shop_id', 'is_active']);
            $table->index(['shop_id', 'stock_on_hand']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
