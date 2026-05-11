<?php

namespace Brigada\Guardian\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeGuardianQueueJobsCommand extends Command
{
    /**
     * Appears in serialized queue payload JSON (displayName / SerializesModels).
     */
    private const PAYLOAD_MARKER = 'SendToNightwatchClient';

    protected $signature = 'guardian:purge-queue-jobs
                            {--dry-run : Show counts only — do not delete}
                            {--skip-failed : Do not prune the failed_jobs table}';

    protected $description = 'Delete pending/failed Guardian hub ingest jobs — run BEFORE composer remove to avoid PHP_Incomplete_Class worker errors';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $skipFailed = (bool) $this->option('skip-failed');

        if ($dryRun) {
            $this->warn('DRY RUN — no rows will be deleted.');
            $this->newLine();
        }

        $this->purgePendingJobsOnDatabaseQueue($dryRun);

        $this->newLine();

        if (! $skipFailed) {
            $this->purgeFailedJobsTable($dryRun);
        }

        return self::SUCCESS;
    }

    private function purgePendingJobsOnDatabaseQueue(bool $dryRun): void
    {
        $queueConnectionName = (string) config('queue.default', 'database');
        $connectionConfig = config("queue.connections.{$queueConnectionName}", []);
        $driver = $connectionConfig['driver'] ?? 'sync';

        if ($driver !== 'database') {
            $queueName = (string) ($connectionConfig['queue'] ?? 'default');
            $this->warn("Your default queue connection [{$queueConnectionName}] uses driver [{$driver}], not \"database\".");
            $this->line('Guardian jobs are still queued on that backend — this command cannot remove them selectively from SQL.');
            $this->line('Stop queue workers first, then for example clear the backlog:');
            $this->line("  php artisan queue:clear {$queueConnectionName} --queue={$queueName}");

            return;
        }

        $jobsTable = (string) ($connectionConfig['table'] ?? 'jobs');
        $dbConnection = $connectionConfig['connection'] ?? config('database.default');

        if (! Schema::connection($dbConnection)->hasTable($jobsTable)) {
            $this->warn("Jobs table \"{$jobsTable}\" not found — skipped pending-job purge.");

            return;
        }

        $query = DB::connection((string) $dbConnection)->table($jobsTable)
            ->where('payload', 'like', '%'.self::PAYLOAD_MARKER.'%');

        $pending = $query->count();

        if ($dryRun) {
            $this->line("Would delete [{$pending}] pending Guardian job row(s) from \"{$jobsTable}\" (DB connection: {$dbConnection}).");

            return;
        }

        if ($pending === 0) {
            $this->line('No pending Guardian ingest jobs found in database queue.');
            return;
        }

        $deleted = DB::connection((string) $dbConnection)->table($jobsTable)
            ->where('payload', 'like', '%'.self::PAYLOAD_MARKER.'%')
            ->delete();
        $this->info("Deleted [{$deleted}] pending Guardian ingest job row(s).");
    }

    private function purgeFailedJobsTable(bool $dryRun): void
    {
        $failedConfig = config('queue.failed');

        if (! is_array($failedConfig)) {
            $this->warn('queue.failed is not configured — skipped.');

            return;
        }

        $failedDriver = (string) ($failedConfig['driver'] ?? '');

        if (! in_array(strtolower($failedDriver), ['database', 'database-uuids'], true)) {
            $this->warn("Failed jobs driver is [{$failedDriver}] — pruning only applies when failed jobs live in SQL (database / database-uuids). Skipped.");

            return;
        }

        $failedTable = (string) ($failedConfig['table'] ?? 'failed_jobs');
        $dbConnection = (string) ($failedConfig['database'] ?? config('database.default'));

        if (! Schema::connection($dbConnection)->hasTable($failedTable)) {
            $this->warn("Table \"{$failedTable}\" not found — skipped failed-job purge.");

            return;
        }

        $failedQuery = DB::connection($dbConnection)->table($failedTable)
            ->where('payload', 'like', '%'.self::PAYLOAD_MARKER.'%');

        $failedCount = $failedQuery->count();

        if ($dryRun) {
            $this->line("Would delete [{$failedCount}] Guardian failed job row(s) from \"{$failedTable}\".");

            return;
        }

        if ($failedCount === 0) {
            $this->line('No Guardian ingest rows matched in failed_jobs.');

            return;
        }

        $deletedFailed = DB::connection($dbConnection)->table($failedTable)
            ->where('payload', 'like', '%'.self::PAYLOAD_MARKER.'%')
            ->delete();
        $this->info("Deleted [{$deletedFailed}] Guardian failed job row(s) from \"{$failedTable}\".");
    }
}
