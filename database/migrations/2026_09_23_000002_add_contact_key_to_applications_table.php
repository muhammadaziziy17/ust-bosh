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
            $table->string('contact_key', 300)->nullable()->after('phone_normalized');
            $table->unique('contact_key');
        });

        // Mavjud arizalar uchun contact_key ni to'ldiramiz.
        DB::table('applications')
            ->select(['id', 'email', 'phone_normalized'])
            ->orderBy('id')
            ->chunkById(100, function ($applications): void {
                foreach ($applications as $application) {
                    if ($application->email === null || $application->phone_normalized === null) {
                        continue;
                    }

                    DB::table('applications')
                        ->where('id', $application->id)
                        ->update([
                            'contact_key' => $application->email.'|'.$application->phone_normalized,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->dropUnique(['contact_key']);
            $table->dropColumn('contact_key');
        });
    }
};
