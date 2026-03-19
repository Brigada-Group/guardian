<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Results\CheckResult;

class FullReportCheck implements HealthCheck
{
    public function name(): string
    {
        return 'Weekly Full Report';
    }

    public function schedule(): Schedule
    {
        return Schedule::Weekly;
    }

    public function run(): CheckResult
    {
        $thisWeek = GuardianResult::where('created_at', '>=', now()->subWeek())->get();
        $lastWeek = GuardianResult::whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->get();

        $thisWeekFailed = $thisWeek->where('status', 'critical')->count();
        $lastWeekFailed = $lastWeek->where('status', 'critical')->count();

        $thisWeekWarnings = $thisWeek->where('status', 'warning')->count();
        $lastWeekWarnings = $lastWeek->where('status', 'warning')->count();

        $trends = [];
        $trends[] = "Critical alerts: {$thisWeekFailed} this week vs {$lastWeekFailed} last week";
        $trends[] = "Warnings: {$thisWeekWarnings} this week vs {$lastWeekWarnings} last week";

        $latestPerCheck = $thisWeek->groupBy('check_class')->map(fn ($results) => $results->sortByDesc('created_at')->first());

        $overallStatus = Status::Ok;
        if ($latestPerCheck->contains(fn ($r) => $r->status === 'critical')) {
            $overallStatus = Status::Critical;
        } elseif ($latestPerCheck->contains(fn ($r) => $r->status === 'warning')) {
            $overallStatus = Status::Warning;
        }

        return new CheckResult(
            $overallStatus,
            implode("\n", $trends),
            [
                'this_week_critical' => $thisWeekFailed,
                'last_week_critical' => $lastWeekFailed,
                'this_week_warnings' => $thisWeekWarnings,
                'last_week_warnings' => $lastWeekWarnings,
            ],
        );
    }
}
