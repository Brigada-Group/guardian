<?php

namespace Brigada\Guardian\Tests\Unit\Listeners;

use Brigada\Guardian\Listeners\CommandListener;
use Brigada\Guardian\Models\CommandLog;
use Brigada\Guardian\Tests\TestCase;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;

class CommandListenerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_logs_completed_commands(): void
    {
        $listener = new CommandListener();

        $input = new ArrayInput([]);
        $output = new NullOutput();

        $listener->handleStarting(new CommandStarting('migrate', $input, $output));
        $listener->handleFinished(new CommandFinished('migrate', $input, $output, 0));

        $this->assertDatabaseHas('guardian_command_logs', [
            'command' => 'migrate',
            'exit_code' => 0,
        ]);
    }

    public function test_it_logs_failed_commands(): void
    {
        $listener = new CommandListener();

        $input = new ArrayInput([]);
        $output = new NullOutput();

        $listener->handleStarting(new CommandStarting('custom:import', $input, $output));
        $listener->handleFinished(new CommandFinished('custom:import', $input, $output, 1));

        $this->assertDatabaseHas('guardian_command_logs', [
            'command' => 'custom:import',
            'exit_code' => 1,
        ]);
    }

    public function test_it_ignores_guardian_commands(): void
    {
        $listener = new CommandListener();

        $input = new ArrayInput([]);
        $output = new NullOutput();

        $listener->handleFinished(new CommandFinished('guardian:run', $input, $output, 0));
        $listener->handleFinished(new CommandFinished('schedule:run', $input, $output, 0));
        $listener->handleFinished(new CommandFinished('queue:work', $input, $output, 0));

        $this->assertDatabaseCount('guardian_command_logs', 0);
    }
}
