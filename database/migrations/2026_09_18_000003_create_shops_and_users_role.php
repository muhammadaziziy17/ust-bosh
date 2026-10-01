<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('pending'); // pending | active | suspended
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default('owner')->after('password'); // super_admin | owner | warehouse | cashier
            $table->foreignId('shop_id')->nullable()->after('role')->constrained('shops')->nullOnDelete();
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_id');
            $table->dropColumn('role');
        });

        Schema::dropIfExists('shops');

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['email']);
        });
    }
};
