<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->string('movement_type', 20); // receipt | sale
            $table->integer('quantity_delta');
            $table->unsignedInteger('stock_after');
            $table->string('reference_type', 30);
            $table->unsignedBigInteger('reference_id');
            $table->unsignedBigInteger('reference_line_id')->nullable();
            $table->string('movement_key', 90);
            $table->foreignId('actor_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->nullable();

            $table->unique(['shop_id', 'movement_key']);
            $table->index(['shop_id', 'product_variant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
