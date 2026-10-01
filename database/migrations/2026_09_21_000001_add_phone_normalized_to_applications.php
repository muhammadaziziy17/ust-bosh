<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->string('phone_normalized', 32)->nullable()->after('phone');
            $table->index('phone_normalized');
        });

        DB::table('applications')
            ->select(['id', 'phone'])
            ->orderBy('id')
            ->chunkById(100, function ($applications): void {
                foreach ($applications as $application) {
                    DB::table('applications')
                        ->where('id', $application->id)
                        ->update([
                            'phone_normalized' => preg_replace('/\D+/', '', (string) $application->phone),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropIndex(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }
};
