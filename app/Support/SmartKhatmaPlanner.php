<?php

namespace App\Support;

class SmartKhatmaPlanner
{
    public const int DAILY_PAGES_CAP = 25;
    public const int EXTENSION_DAYS_LIMIT = 7;
    public const int ROUNDING_STEP = 5;

    public static function calculateRawDailyTarget(int $remainingPages, int $remainingDays): int
    {
        if ($remainingPages <= 0) {
            return 0;
        }

        return (int) ceil($remainingPages / max($remainingDays, 1));
    }

    public static function roundDailyTarget(
        int $rawDailyTarget,
        int $remainingPages,
        int $step = self::ROUNDING_STEP,
    ): int {
        if ($remainingPages <= 0 || $rawDailyTarget <= 0) {
            return 0;
        }

        $step = max($step, 1);

        if ($remainingPages <= $step) {
            return $remainingPages;
        }

        $rounded = (int) (round($rawDailyTarget / $step) * $step);

        if ($rounded < $step) {
            $rounded = $step;
        }

        return min($rounded, $remainingPages);
    }

    public static function calculateRoundedDailyTarget(int $remainingPages, int $remainingDays): int
    {
        $raw = self::calculateRawDailyTarget($remainingPages, $remainingDays);

        return self::roundDailyTarget($raw, $remainingPages);
    }

    public static function requiredExtraDaysForDailyCap(
        int $remainingPages,
        int $remainingDays,
        int $dailyCap = self::DAILY_PAGES_CAP,
    ): int {
        if ($remainingPages <= 0) {
            return 0;
        }

        $dailyCap = max($dailyCap, 1);
        $remainingDays = max($remainingDays, 1);

        $requiredDays = (int) ceil($remainingPages / $dailyCap);

        return max($requiredDays - $remainingDays, 0);
    }

    public static function resolveAutoExtension(
        int $remainingPages,
        int $remainingDays,
        int $extensionDaysUsed,
        int $dailyCap = self::DAILY_PAGES_CAP,
        int $extensionDaysLimit = self::EXTENSION_DAYS_LIMIT,
    ): array {
        $remainingDays = max($remainingDays, 1);
        $dailyCap = max($dailyCap, 1);
        $extensionDaysLimit = max($extensionDaysLimit, 0);
        $extensionDaysUsed = max($extensionDaysUsed, 0);

        $availableExtension = max($extensionDaysLimit - $extensionDaysUsed, 0);
        $rawDailyBefore = self::calculateRawDailyTarget($remainingPages, $remainingDays);
        $requiredExtension = self::requiredExtraDaysForDailyCap($remainingPages, $remainingDays, $dailyCap);
        $appliedExtension = min($requiredExtension, $availableExtension);

        $daysAfterExtension = $remainingDays + $appliedExtension;
        $rawDailyAfter = self::calculateRawDailyTarget($remainingPages, $daysAfterExtension);
        $suggestedDaily = self::roundDailyTarget($rawDailyAfter, $remainingPages);

        return [
            'raw_daily_before' => $rawDailyBefore,
            'required_extension_days' => $requiredExtension,
            'applied_extension_days' => $appliedExtension,
            'days_after_extension' => $daysAfterExtension,
            'extension_days_used_after' => $extensionDaysUsed + $appliedExtension,
            'extension_days_remaining_after' => max($availableExtension - $appliedExtension, 0),
            'raw_daily_after_extension' => $rawDailyAfter,
            'needs_higher_daily_pages' => $rawDailyAfter > $dailyCap,
            'suggested_daily_pages' => $suggestedDaily,
        ];
    }
}
