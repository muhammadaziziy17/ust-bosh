<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('receipt_no', 32);
            $table->string('status', 20)->default('posted'); // posted | cancelled
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('posted_by')->nullable()->constrained('users');
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('received_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['shop_id', 'receipt_no']);
            $table->index(['shop_id', 'status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_receipts');
    }
};
