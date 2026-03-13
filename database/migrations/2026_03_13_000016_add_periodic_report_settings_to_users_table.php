<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('weekly_reports_enabled')
                ->default(true)
                ->after('wird_reminders_time');
            $table->boolean('monthly_reports_enabled')
                ->default(true)
                ->after('weekly_reports_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'weekly_reports_enabled',
                'monthly_reports_enabled',
            ]);
        });
    }
};
