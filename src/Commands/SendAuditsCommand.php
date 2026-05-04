<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Audits\ComposerAuditReporter;
use Brigada\Guardian\Audits\NpmAuditReporter;
use Brigada\Guardian\Support\TraceContext;
use Brigada\Guardian\Transport\NightwatchClient;
use Brigada\Guardian\Transport\SendToNightwatchClient;
use Illuminate\Console\Command;

class SendAuditsCommand extends Command
{
    protected $signature = 'guardian:audits';

    protected $description = 'Run composer audit and npm audit and forward the results to Nightwatch';

    public function handle(
        ComposerAuditReporter $composer,
        NpmAuditReporter $npm,
        NightwatchClient $client,
    ): int {
        $environment = config('guardian.environment', config('app.env'));

        if (! in_array($environment, config('guardian.enabled_environments', ['production']))) {
            $this->warn("Guardian is not enabled for environment: {$environment}");
            return 0;
        }

        if (! $client->isConfigured()) {
            $this->warn('Guardian hub is not configured; skipping audit forwarding.');
            return 0;
        }

        $async = config('guardian.hub.async', true);

        $composerPayload = $composer->build();
        if ($composerPayload !== null) {
            $this->dispatch('composer-audit', $composerPayload, $async);
            $this->info("Composer audit sent ({$composerPayload['advisories_count']} advisories, {$composerPayload['abandoned_count']} abandoned).");
        } else {
            $this->line('Composer audit skipped (no composer.json or command failed).');
        }

        $npmPayload = $npm->build();
        if ($npmPayload !== null) {
            $this->dispatch('npm-audit', $npmPayload, $async);
            $this->info("NPM audit sent ({$npmPayload['total_vulnerabilities']} vulnerabilities).");
        } else {
            $this->line('NPM audit skipped (no package-lock.json or command failed).');
        }

        return 0;
    }

    private function dispatch(string $endpoint, array $payload, bool $async): void
    {
        $ingestPayload = $payload + ['trace_id' => TraceContext::current()];

        if ($async) {
            SendToNightwatchClient::dispatch($endpoint, $ingestPayload);
            return;
        }

        app(NightwatchClient::class)->send($endpoint, $ingestPayload);
    }
}
