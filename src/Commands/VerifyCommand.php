<?php

namespace Brigada\Guardian\Commands;

use Brigada\Guardian\Transport\HeartbeatSender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VerifyCommand extends Command
{
    protected $signature = 'guardian:verify {token : 6-digit token shown in the Nightwatch UI}';

    protected $description = 'Acknowledge a Nightwatch connection-verification token';

    public function handle(HeartbeatSender $heartbeat): int
    {
        $token = (string) $this->argument('token');

        if (! preg_match('/^[0-9]{6}$/', $token)) {
            $this->error('Token must be exactly 6 digits.');
            return self::INVALID;
        }

        // Stash the token where the next heartbeat will pick it up. Going
        // through the cache (instead of POSTing inline here) means the
        // verification still completes if the hub is briefly unreachable —
        // the next scheduled heartbeat will retry naturally.
        Cache::put(HeartbeatSender::VERIFY_TOKEN_CACHE_KEY, $token, now()->addMinutes(5));

        // Then send a heartbeat right now so the user gets fast feedback
        // instead of waiting up to five minutes for the next scheduled tick.
        try {
            $heartbeat->sendNow();
            $this->info('Verification heartbeat sent. Check the Nightwatch UI — should flip to verified within seconds.');
        } catch (\Throwable $e) {
            $this->warn('Could not send heartbeat now: '.$e->getMessage());
            $this->line('The token has been stashed; the next scheduled heartbeat will deliver it.');
        }

        return self::SUCCESS;
    }
}
