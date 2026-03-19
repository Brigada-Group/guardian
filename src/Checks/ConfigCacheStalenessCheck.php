<?php

namespace Brigada\Guardian\Checks;

use Brigada\Guardian\Contracts\HealthCheck;
use Brigada\Guardian\Enums\Schedule;
use Brigada\Guardian\Enums\Status;
use Brigada\Guardian\Results\CheckResult;

class ConfigCacheStalenessCheck implements HealthCheck
{
    public function name(): string { return 'Config Cache'; }
    public function schedule(): Schedule { return Schedule::Daily; }

    public function run(): CheckResult
    {
        $cacheFile = base_path('bootstrap/cache/config.php');
        if (! file_exists($cacheFile)) {
            return new CheckResult(Status::Warning, 'Config is not cached — run php artisan config:cache');
        }
        $cacheTime = filemtime($cacheFile);
        $configDir = config_path();
        $newestConfig = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($configDir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $newestConfig = max($newestConfig, $file->getMTime());
            }
        }
        $envFile = base_path('.env');
        if (file_exists($envFile)) {
            $newestConfig = max($newestConfig, filemtime($envFile));
        }
        if ($newestConfig > $cacheTime) {
            return new CheckResult(Status::Warning, 'Config cache is stale — config files changed since last cache', ['cache_time' => date('Y-m-d H:i:s', $cacheTime), 'newest_config' => date('Y-m-d H:i:s', $newestConfig)]);
        }
        return new CheckResult(Status::Ok, 'Config cache is fresh');
    }
}
