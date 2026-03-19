<?php

namespace Brigada\Guardian\Support;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Results\CheckResult;

class Deduplicator
{
    public function shouldNotify(string $checkClass, CheckResult $result): bool
    {
        if ($result->status === Status::Ok) {
            return false;
        }

        $dedupMinutes = config('guardian.notifications.dedup_minutes', 60);

        $lastNotified = GuardianResult::where('check_class', $checkClass)
            ->whereNotNull('notified_at')
            ->where('status', $result->status->value)
            ->latest('notified_at')
            ->first();

        if (! $lastNotified) {
            return true;
        }

        return $lastNotified->notified_at->diffInMinutes(now()) >= $dedupMinutes;
    }

    public function record(string $checkClass, CheckResult $result, bool $notified): void
    {
        GuardianResult::create([
            'check_class' => $checkClass,
            'status' => $result->status->value,
            'message' => $result->message,
            'metadata' => $result->metadata,
            'notified_at' => $notified ? now() : null,
            'created_at' => now(),
        ]);
    }

    public function prune(int $days = 30): int
    {
        return GuardianResult::where('created_at', '<', now()->subDays($days))->delete();
    }
}
