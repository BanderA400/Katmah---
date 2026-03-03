<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('default_auto_compensate_missed_days')
                ->default(false)
                ->after('password');
            $table->unsignedSmallInteger('default_daily_pages')
                ->default(5)
                ->after('default_auto_compensate_missed_days');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'default_auto_compensate_missed_days',
                'default_daily_pages',
            ]);
        });
    }
};
