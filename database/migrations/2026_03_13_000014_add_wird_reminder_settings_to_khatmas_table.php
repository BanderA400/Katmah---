<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khatmas', function (Blueprint $table): void {
            $table->boolean('use_custom_reminder_settings')
                ->default(false)
                ->after('smart_extension_days_used');
            $table->boolean('reminder_enabled')
                ->nullable()
                ->after('use_custom_reminder_settings');
            $table->time('reminder_time')
                ->nullable()
                ->after('reminder_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('khatmas', function (Blueprint $table): void {
            $table->dropColumn([
                'use_custom_reminder_settings',
                'reminder_enabled',
                'reminder_time',
            ]);
        });
    }
};
