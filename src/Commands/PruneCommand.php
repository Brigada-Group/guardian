<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Models\CacheLog;
use Brigada\Guardian\Models\CommandLog;
use Brigada\Guardian\Models\GuardianResult;
use Brigada\Guardian\Models\MailLog;
use Brigada\Guardian\Models\NotificationLog;
use Brigada\Guardian\Models\OutgoingHttpLog;
use Brigada\Guardian\Models\QueryLog;
use Brigada\Guardian\Models\RequestLog;
use Brigada\Guardian\Models\ScheduledTaskLog;
use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'guardian:prune
        {--days= : Override default retention days for all tables}
        {--dry-run : Show what would be deleted without actually deleting}';

    protected $description = 'Delete old Guardian monitoring data based on retention settings';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $overrideDays = $this->option('days') ? (int) $this->option('days') : null;

        if ($dryRun) {
            $this->warn('DRY RUN — no data will be deleted.');
            $this->newLine();
        }

        $tables = [
            ['model' => GuardianResult::class, 'config' => 'results_days', 'label' => 'Check results'],
            ['model' => RequestLog::class, 'config' => 'request_logs_days', 'label' => 'Request logs'],
            ['model' => OutgoingHttpLog::class, 'config' => 'outgoing_http_logs_days', 'label' => 'Outgoing HTTP logs'],
            ['model' => QueryLog::class, 'config' => 'query_logs_days', 'label' => 'Query logs'],
            ['model' => MailLog::class, 'config' => 'mail_logs_days', 'label' => 'Mail logs'],
            ['model' => NotificationLog::class, 'config' => 'notification_logs_days', 'label' => 'Notification logs'],
            ['model' => CacheLog::class, 'config' => 'cache_logs_days', 'label' => 'Cache logs'],
            ['model' => CommandLog::class, 'config' => 'command_logs_days', 'label' => 'Command logs'],
            ['model' => ScheduledTaskLog::class, 'config' => 'scheduled_task_logs_days', 'label' => 'Scheduled task logs'],
        ];

        $totalDeleted = 0;

        foreach ($tables as $table) {
            $days = $overrideDays ?? config("guardian.retention.{$table['config']}", 30);
            $cutoff = now()->subDays($days);

            $query = $table['model']::where('created_at', '<', $cutoff);
            $count = $query->count();

            if ($count === 0) {
                $this->line("  {$table['label']}: nothing to prune (retention: {$days} days)");
                continue;
            }

            if ($dryRun) {
                $this->line("  {$table['label']}: would delete {$count} records older than {$days} days");
            } else {
                $deleted = $query->delete();
                $this->line("  {$table['label']}: deleted {$deleted} records older than {$days} days");
                $totalDeleted += $deleted;
            }
        }

        $this->newLine();

        if ($dryRun) {
            $this->info('Dry run complete. No data was deleted.');
        } else {
            $this->info("Pruning complete. {$totalDeleted} total records deleted.");
        }

        return 0;
    }
}
