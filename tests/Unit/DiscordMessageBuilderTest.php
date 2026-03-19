<?php

namespace Brigada\Guardian\Tests\Unit;

use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Notifications\DiscordMessageBuilder;
use Brigada\Guardian\Results\CheckResult;
use PHPUnit\Framework\TestCase;

class DiscordMessageBuilderTest extends TestCase
{
    public function test_it_builds_critical_alert_embed(): void
    {
        $builder = new DiscordMessageBuilder('Client Portal', 'production');

        $payload = $builder->buildAlert('Disk Space', new CheckResult(
            Status::Critical,
            'Disk usage at 94.2% (2.1GB free of 40GB)',
            ['percent_used' => 94.2],
        ));

        $this->assertArrayHasKey('embeds', $payload);
        $embed = $payload['embeds'][0];
        $this->assertSame(0xFF0000, $embed['color']);
        $this->assertStringContainsString('Client Portal', $embed['title']);
        $this->assertStringContainsString('CRITICAL', $embed['title']);
        $this->assertStringContainsString('Disk Space', $embed['title']);
    }

    public function test_it_builds_warning_alert_embed(): void
    {
        $builder = new DiscordMessageBuilder('Client Portal', 'production');

        $payload = $builder->buildAlert('Memory', new CheckResult(
            Status::Warning,
            'Memory at 82%',
        ));

        $embed = $payload['embeds'][0];
        $this->assertSame(0xFFA500, $embed['color']);
        $this->assertStringContainsString('WARNING', $embed['title']);
    }

    public function test_it_builds_daily_summary(): void
    {
        $builder = new DiscordMessageBuilder('Client Portal', 'production');

        $results = [
            'Disk Space' => new CheckResult(Status::Ok, '62.3% used'),
            'Composer Audit' => new CheckResult(Status::Critical, '2 vulnerabilities'),
        ];

        $payload = $builder->buildSummary('Daily Health Summary', $results);

        $embed = $payload['embeds'][0];
        $this->assertStringContainsString('Daily Health Summary', $embed['title']);
        $this->assertCount(2, $embed['fields']);
    }
}
