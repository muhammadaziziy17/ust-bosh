<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('sale_no', 40);
            $table->string('status', 20)->default('completed'); // completed | voided
            $table->foreignId('cashier_id')->constrained('users');
            $table->timestamp('sold_at');
            $table->string('payment_method', 10); // cash | card
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('amount_received', 14, 2)->nullable();
            $table->decimal('change_amount', 14, 2)->nullable();
            $table->string('idempotency_key', 64);
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'sale_no']);
            $table->unique(['shop_id', 'cashier_id', 'idempotency_key']);
            $table->index(['shop_id', 'status', 'sold_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
