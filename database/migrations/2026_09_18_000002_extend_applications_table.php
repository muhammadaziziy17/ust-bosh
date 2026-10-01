<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('email')->after('contact_name');
            $table->string('status')->default('pending')->after('message'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable()->after('status');
            $table->string('locale', 5)->default('uz')->after('rejection_reason');
            $table->foreignId('reviewed_by')->nullable()->after('locale'); // admin user id (kelajakda)
            $table->timestamp('reviewed_at')->nullable()->change();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn(['email', 'status', 'rejection_reason', 'locale', 'reviewed_by']);
        });
    }
};
