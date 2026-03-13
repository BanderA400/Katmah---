<?php

namespace Tests\Unit;

use App\Support\SmartKhatmaPlanner;
use Tests\TestCase;

class SmartKhatmaPlannerTest extends TestCase
{
    public function test_calculates_rounded_daily_target_to_nearest_five_pages(): void
    {
        $target = SmartKhatmaPlanner::calculateRoundedDailyTarget(604, 30);

        $this->assertSame(20, $target);
    }

    public function test_auto_extension_reduces_daily_target_to_cap_when_possible(): void
    {
        $result = SmartKhatmaPlanner::resolveAutoExtension(
            remainingPages: 300,
            remainingDays: 10,
            extensionDaysUsed: 0,
        );

        $this->assertSame(2, $result['applied_extension_days']);
        $this->assertSame(25, $result['raw_daily_after_extension']);
        $this->assertFalse($result['needs_higher_daily_pages']);
    }

    public function test_auto_extension_flags_need_for_higher_daily_target_after_limit_is_used(): void
    {
        $result = SmartKhatmaPlanner::resolveAutoExtension(
            remainingPages: 400,
            remainingDays: 10,
            extensionDaysUsed: 7,
        );

        $this->assertSame(0, $result['applied_extension_days']);
        $this->assertTrue($result['needs_higher_daily_pages']);
        $this->assertSame(40, $result['suggested_daily_pages']);
    }
}
