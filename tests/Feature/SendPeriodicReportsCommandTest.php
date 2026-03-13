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
use App\Notifications\PeriodicProgressReportNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendPeriodicReportsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        Event::forget(NotificationSending::class);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_weekly_report_is_sent_as_mail_and_in_app_and_is_idempotent(): void
    {
        Carbon::setTestNow('2026-03-13 20:00:00');

        $user = User::factory()->create();
        $khatma = $this->createActiveKhatma($user, 'ختمة أسبوعية');

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-10',
            'from_page' => 1,
            'to_page' => 5,
            'pages_count' => 5,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-12',
            'from_page' => 6,
            'to_page' => 10,
            'pages_count' => 5,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('khatma:send-periodic-reports weekly')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            PeriodicProgressReportNotification::class,
            function (PeriodicProgressReportNotification $notification, array $channels): bool {
                return $channels === ['mail'];
            },
        );
        Notification::assertSentTo(
            $user,
            PeriodicProgressReportNotification::class,
            function (PeriodicProgressReportNotification $notification, array $channels): bool {
                return $channels === ['database'];
            },
        );

        $this->artisan('khatma:send-periodic-reports weekly')->assertExitCode(0);
        Notification::assertSentToTimes($user, PeriodicProgressReportNotification::class, 2);
    }

    public function test_monthly_report_is_sent_even_without_any_progress(): void
    {
        Carbon::setTestNow('2026-04-01 09:00:00');

        $user = User::factory()->create();
        $this->createActiveKhatma($user, 'ختمة بلا إنجاز');

        Notification::fake();

        $this->artisan('khatma:send-periodic-reports monthly')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            PeriodicProgressReportNotification::class,
            function (PeriodicProgressReportNotification $notification, array $channels) use ($user): bool {
                if (! in_array('mail', $channels, true)) {
                    return false;
                }

                $mail = $notification->toMail($user);

                return in_array(
                    'شهر جديد فرصة جديدة، ابدأ بخطة خفيفة وثابتة وتدرّج.',
                    $mail->introLines,
                    true,
                );
            },
        );
    }

    public function test_weekly_report_respects_user_toggle(): void
    {
        Carbon::setTestNow('2026-03-13 20:00:00');

        $enabledUser = User::factory()->create([
            'weekly_reports_enabled' => true,
        ]);
        $disabledUser = User::factory()->create([
            'weekly_reports_enabled' => false,
        ]);

        $this->createActiveKhatma($enabledUser, 'ختمة للمفعل');
        $this->createActiveKhatma($disabledUser, 'ختمة للمعطل');

        Notification::fake();

        $this->artisan('khatma:send-periodic-reports weekly')->assertExitCode(0);

        Notification::assertSentTo($enabledUser, PeriodicProgressReportNotification::class);
        Notification::assertNotSentTo($disabledUser, PeriodicProgressReportNotification::class);
    }

    public function test_commitment_is_scoped_to_effective_days_in_period(): void
    {
        Carbon::setTestNow('2026-03-13 20:00:00');

        $user = User::factory()->create();

        $khatma = Khatma::create([
            'user_id' => $user->id,
            'name' => 'ختمة قديمة',
            'type' => KhatmaType::Tilawa,
            'scope' => KhatmaScope::Custom,
            'direction' => KhatmaDirection::Forward,
            'start_page' => 1,
            'end_page' => 50,
            'total_pages' => 50,
            'planning_method' => PlanningMethod::ByWird,
            'auto_compensate_missed_days' => false,
            'daily_pages' => 5,
            'start_date' => '2025-01-01',
            'expected_end_date' => '2025-01-31',
            'status' => KhatmaStatus::Completed,
            'current_page' => 50,
            'completed_pages' => 50,
        ]);

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-12',
            'from_page' => 46,
            'to_page' => 50,
            'pages_count' => 5,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        Notification::fake();

        $this->artisan('khatma:send-periodic-reports weekly')->assertExitCode(0);

        Notification::assertSentTo(
            $user,
            PeriodicProgressReportNotification::class,
            function (PeriodicProgressReportNotification $notification, array $channels) use ($user): bool {
                if (! in_array('mail', $channels, true)) {
                    return false;
                }

                $mail = $notification->toMail($user);

                return in_array('الأيام المتأخرة: 0 يوم', $mail->introLines, true)
                    && in_array('نسبة الالتزام: 100٪', $mail->introLines, true);
            },
        );
    }

    public function test_database_notification_is_not_duplicated_when_mail_fails_then_retries(): void
    {
        Carbon::setTestNow('2026-03-13 20:00:00');

        $user = User::factory()->create();
        $khatma = $this->createActiveKhatma($user, 'ختمة اختبار الفشل');

        DailyRecord::create([
            'khatma_id' => $khatma->id,
            'user_id' => $user->id,
            'date' => '2026-03-12',
            'from_page' => 1,
            'to_page' => 5,
            'pages_count' => 5,
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        $shouldFailOnce = true;

        Event::listen(NotificationSending::class, function (NotificationSending $event) use ($user, &$shouldFailOnce): void {
            if (
                $shouldFailOnce
                && $event->channel === 'mail'
                && $event->notification instanceof PeriodicProgressReportNotification
                && $event->notifiable instanceof User
                && (int) $event->notifiable->id === (int) $user->id
            ) {
                $shouldFailOnce = false;

                throw new \RuntimeException('Simulated mail failure.');
            }
        });

        $this->artisan('khatma:send-periodic-reports weekly')->assertExitCode(0);

        $this->assertDatabaseMissing('notifications', [
            'type' => PeriodicProgressReportNotification::class,
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
        ]);

        Event::forget(NotificationSending::class);

        $this->artisan('khatma:send-periodic-reports weekly')->assertExitCode(0);

        $dbCount = DB::table('notifications')
            ->where('type', PeriodicProgressReportNotification::class)
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $user->id)
            ->count();

        $this->assertSame(1, $dbCount);
    }

    private function createActiveKhatma(User $user, string $name): Khatma
    {
        return Khatma::create([
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
            'start_date' => Carbon::today()->subDays(20),
            'expected_end_date' => Carbon::today()->addDays(20),
            'status' => KhatmaStatus::Active,
            'current_page' => 1,
            'completed_pages' => 0,
        ]);
    }
}
