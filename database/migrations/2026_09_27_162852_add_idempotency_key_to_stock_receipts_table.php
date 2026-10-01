<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table): void {
            $table->string('idempotency_key', 64)->nullable()->after('receipt_no');
            $table->unique(['shop_id', 'created_by', 'idempotency_key'], 'stock_receipts_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::table('stock_receipts', function (Blueprint $table): void {
            $table->dropUnique('stock_receipts_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
