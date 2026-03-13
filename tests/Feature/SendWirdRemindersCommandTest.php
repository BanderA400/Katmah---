<?php

namespace Tests\Feature;

use App\Enums\KhatmaDirection;
use App\Enums\KhatmaScope;
use App\Enums\KhatmaStatus;
use App\Enums\KhatmaType;
use App\Enums\PlanningMethod;
use App\Models\DailyRecord;
use App\Models\Khatma;
use App\Models\User;
use App\Notifications\DelayedWirdReminderNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendWirdRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_command_sends_delayed_reminders_as_mail_and_in_app(): void
    {
        Carbon::setTestNow('2026-03-16 20:00:00');

        $user = User::factory()->create([
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
        ]);

        Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة متأخرة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 100,
            'total_pages' => 100,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 10,
            'start_date' => Carbon::today()->subDays(2),
            'expected_end_date' => Carbon::today()->addDays(7),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);

        Notification::fake();

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            DelayedWirdReminderNotification::class,
            function (DelayedWirdReminderNotification $notification, array $channels): bool {
                return in_array('database', $channels, true)
                    && in_array('mail', $channels, true);
            },
        );

        // Idempotent per day: running again should not duplicate.
        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($user, DelayedWirdReminderNotification::class, 1);
    }

    public function test_command_skips_user_when_today_wird_is_completed(): void
    {
        Carbon::setTestNow('2026-03-16 20:00:00');

        $user = User::factory()->create([
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
        ]);

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة منجزة اليوم',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 100,
            'total_pages' => 100,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 10,
            'start_date' => Carbon::today()->subDays(2),
            'expected_end_date' => Carbon::today()->addDays(7),
            'status' => KhatmaStatus::Active,
            'current_page' => 11,
            'completed_pages' => 10,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 1,
            'to_page' => 10,
            'pages_count' => 10,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_command_respects_smart_extension_rebalanced_daily_target_for_duration_plan(): void
    {
        Carbon::setTestNow('2026-03-16 20:00:00');

        $user = User::factory()->create([
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
        ]);

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة مدة ذكية',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 300,
            'total_pages' => 300,
            'planning_method' => PlanningMethod::ByDuration,
            'auto_compensate_missed_days' => true,
            'smart_extension_days_used' => 0,
            'daily_pages' => 30,
            'start_date' => Carbon::today()->subDays(5),
            'expected_end_date' => Carbon::today()->addDays(9), // 10 days left including today
            'status' => KhatmaStatus::Active,
            'current_page' => 26,
            'completed_pages' => 25,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => Carbon::today(),
            'from_page' => 1,
            'to_page' => 25,
            'pages_count' => 25,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);

        // With smart extension, today's effective target is 25 pages, so no reminder should be sent.
        Notification::assertNothingSent();
    }

    public function test_custom_khatma_reminder_can_override_disabled_user_setting(): void
    {
        Carbon::setTestNow('2026-03-16 20:00:00');

        $user = User::factory()->create([
            'wird_reminders_enabled' => false,
            'wird_reminders_time' => '20:00:00',
        ]);

        $this->createDelayedByWirdKhatma($user, 'ختمة بإعداد خاص', [
            'use_custom_reminder_settings' => true,
            'reminder_enabled' => true,
            'reminder_time' => '20:00:00',
        ]);

        Notification::fake();

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);

        Notification::assertSentTo($user, DelayedWirdReminderNotification::class);
    }

    public function test_custom_khatma_reminder_time_is_respected_over_user_default_time(): void
    {
        Carbon::setTestNow('2026-03-16 20:00:00');

        $user = User::factory()->create([
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
        ]);

        $this->createDelayedByWirdKhatma($user, 'ختمة بوقت مختلف', [
            'use_custom_reminder_settings' => true,
            'reminder_enabled' => true,
            'reminder_time' => '21:00:00',
        ]);

        Notification::fake();

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);
        Notification::assertNothingSent();

        Carbon::setTestNow('2026-03-16 21:00:00');

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);
        Notification::assertSentToTimes($user, DelayedWirdReminderNotification::class, 1);
    }

    public function test_command_continues_when_one_user_fails_and_retries_failed_user_next_run(): void
    {
        Carbon::setTestNow('2026-03-16 20:00:00');

        $failedUser = User::factory()->create([
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
        ]);

        $healthyUser = User::factory()->create([
            'wird_reminders_enabled' => true,
            'wird_reminders_time' => '20:00:00',
        ]);

        $this->createDelayedByWirdKhatma($failedUser, 'ختمة فاشلة');
        $this->createDelayedByWirdKhatma($healthyUser, 'ختمة سليمة');

        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($failedUser): void {
            if (
                $event->notification instanceof DelayedWirdReminderNotification
                && $event->notifiable instanceof User
                && (int) $event->notifiable->id === (int) $failedUser->id
            ) {
                throw new \RuntimeException('Simulated reminder failure for test.');
            }
        });

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => DelayedWirdReminderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $failedUser->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'type' => DelayedWirdReminderNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $healthyUser->id,
        ]);

        Event::forget(NotificationSending::class);

        $this->artisan('khatma:send-wird-reminders')->assertExitCode(0);

        $failedCount = DB::table('notifications')
            ->where('type', DelayedWirdReminderNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $failedUser->id)
            ->count();

        $healthyCount = DB::table('notifications')
            ->where('type', DelayedWirdReminderNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $healthyUser->id)
            ->count();

        $this->assertSame(1, $failedCount);
        $this->assertSame(1, $healthyCount);
    }

    private function createDelayedByWirdKhatma(User $user, string $name, array $overrides = []): Khatma
    {
        return Khatma::create(array_merge([
            'user_id' => $user->id,
            'name' => $name,
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 100,
            'total_pages' => 100,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 10,
            'start_date' => Carbon::today()->subDays(2),
            'expected_end_date' => Carbon::today()->addDays(7),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ], $overrides));
    }
}
