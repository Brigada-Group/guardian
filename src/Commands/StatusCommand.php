<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Models\GuardianResult;
use Illuminate\Console\Command;

class StatusCommand extends Command
{
    protected $signature = 'guardian:status';
    protected $description = 'Show the latest Guardian check results locally';

    public function handle(): int
    {
        $latestResults = GuardianResult::query()
            ->selectRaw('check_class, status, message, MAX(created_at) as last_run')
            ->groupBy('check_class', 'status', 'message')
            ->orderBy('last_run', 'desc')
            ->get();

        if ($latestResults->isEmpty()) {
            $this->warn('No check results found. Run guardian:run first.');
            return 0;
        }

        $rows = $latestResults->map(fn ($r) => [
            $r->check_class,
            strtoupper($r->status),
            \Illuminate\Support\Str::limit($r->message, 60),
            $r->last_run,
        ])->toArray();

        $this->table(['Check', 'Status', 'Message', 'Last Run'], $rows);

        return 0;
    }
}
