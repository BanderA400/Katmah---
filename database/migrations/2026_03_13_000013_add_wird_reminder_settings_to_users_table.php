<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('wird_reminders_enabled')
                ->default(true)
                ->after('default_daily_pages');
            $table->time('wird_reminders_time')
                ->default('20:00:00')
                ->after('wird_reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'wird_reminders_enabled',
                'wird_reminders_time',
            ]);
        });
    }
};
