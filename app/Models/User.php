<?php

namespace App\Models;

use App\Support\AppSettings;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, MustVerifyEmailContract
{
    use HasFactory, MustVerifyEmail, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'default_auto_compensate_missed_days',
        'default_daily_pages',
        'wird_reminders_enabled',
        'wird_reminders_time',
        'weekly_reports_enabled',
        'monthly_reports_enabled',
        'is_admin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if ($user->default_auto_compensate_missed_days === null) {
                $user->default_auto_compensate_missed_days = (bool) AppSettings::get(
                    AppSettings::KEY_GLOBAL_DEFAULT_AUTO_COMPENSATE,
                    false,
                );
            }

            if ($user->default_daily_pages === null) {
                $globalDefault = (int) AppSettings::get(
                    AppSettings::KEY_GLOBAL_DEFAULT_DAILY_PAGES,
                    5,
                );

                $user->default_daily_pages = max(min($globalDefault, 604), 1);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'default_auto_compensate_missed_days' => 'boolean',
            'default_daily_pages' => 'integer',
            'wird_reminders_enabled' => 'boolean',
            'wird_reminders_time' => 'string',
            'weekly_reports_enabled' => 'boolean',
            'monthly_reports_enabled' => 'boolean',
            'is_admin' => 'boolean',
        ];
    }

    // ==================
    // Filament
    // ==================

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'control') {
            return (bool) $this->is_admin;
        }

        return true;
    }

    public function hasVerifiedEmail(): bool
    {
        if ((bool) $this->is_admin) {
            return true;
        }

        return ! is_null($this->email_verified_at);
    }

    // ==================
    // العلاقات
    // ==================

    public function khatmas(): HasMany
    {
        return $this->hasMany(Khatma::class);
    }

    public function dailyRecords(): HasMany
    {
        return $this->hasMany(DailyRecord::class);
    }
}
