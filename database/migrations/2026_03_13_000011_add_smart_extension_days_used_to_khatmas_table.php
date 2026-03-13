<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khatmas', function (Blueprint $table): void {
            $table->unsignedTinyInteger('smart_extension_days_used')
                ->default(0)
                ->after('auto_compensate_missed_days');
        });
    }

    public function down(): void
    {
        Schema::table('khatmas', function (Blueprint $table): void {
            $table->dropColumn('smart_extension_days_used');
        });
    }
};
