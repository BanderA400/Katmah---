<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->index(
                ['wird_reminders_enabled', 'wird_reminders_time'],
                'users_wird_reminders_idx',
            );
        });

        Schema::table('khatmas', function (Blueprint $table): void {
            $table->index(
                ['status', 'use_custom_reminder_settings', 'reminder_enabled', 'reminder_time'],
                'khatmas_reminder_due_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('khatmas', function (Blueprint $table): void {
            $table->dropIndex('khatmas_reminder_due_idx');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_wird_reminders_idx');
        });
    }
};
