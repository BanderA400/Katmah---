<?php

namespace App\Models;

use App\Enums\KhatmaType;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaDirection;
use App\Enums\PlanningMethod;
use App\Enums\KhatmaStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Khatma extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'type',
        'scope',
        'direction',
        'start_page',
        'end_page',
        'total_pages',
        'planning_method',
        'auto_compensate_missed_days',
        'smart_extension_days_used',
        'use_custom_reminder_settings',
        'reminder_enabled',
        'reminder_time',
        'daily_pages',
        'start_date',
        'expected_end_date',
        'status',
        'current_page',
        'completed_pages',
    ];

    protected $casts = [
        'type' => KhatmaType::class,
        'scope' => KhatmaScope::class,
        'direction' => KhatmaDirection::class,
        'planning_method' => PlanningMethod::class,
        'status' => KhatmaStatus::class,
        'auto_compensate_missed_days' => 'boolean',
        'smart_extension_days_used' => 'integer',
        'use_custom_reminder_settings' => 'boolean',
        'reminder_enabled' => 'boolean',
        'reminder_time' => 'string',
        'start_page' => 'integer',
        'end_page' => 'integer',
        'total_pages' => 'integer',
        'daily_pages' => 'integer',
        'current_page' => 'integer',
        'completed_pages' => 'integer',
        'start_date' => 'date',
        'expected_end_date' => 'date',
    ];

    // ==================
    // العلاقات
    // ==================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dailyRecords(): HasMany
    {
        return $this->hasMany(DailyRecord::class);
    }

    // ==================
    // Scopes
    // ==================

    public function scopeActive($query)
    {
        return $query->where('status', KhatmaStatus::Active);
    }

    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // ==================
    // الحسابات
    // ==================

    /**
     * نسبة الإنجاز
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_pages === 0) return 0;
        return round(($this->completed_pages / $this->total_pages) * 100, 1);
    }

    /**
     * الصفحات المتبقية
     */
    public function getRemainingPagesAttribute(): int
    {
        return $this->total_pages - $this->completed_pages;
    }

    /**
     * السورة الحالية
     */
    public function getCurrentSurahAttribute(): ?Surah
    {
        return Surah::getByPage($this->current_page);
    }

    /**
     * ورد اليوم — من صفحة
     */
    public function getTodayFromPageAttribute(): int
    {
        return $this->current_page;
    }

    /**
     * ورد اليوم — إلى صفحة
     */
    public function getTodayToPageAttribute(): int
    {
        if ($this->direction === KhatmaDirection::Backward) {
            $toPage = $this->current_page - $this->daily_pages + 1;

            return max($toPage, $this->start_page);
        }

        $toPage = $this->current_page + $this->daily_pages - 1;

        return min($toPage, $this->end_page);
    }
}
