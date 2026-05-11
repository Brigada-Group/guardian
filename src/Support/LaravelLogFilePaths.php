<?php

namespace Brigada\Guardian\Support;

use Carbon\Carbon;

final class LaravelLogFilePaths
{
    /**
     * @param  array<int, string>  $channelNames
     * @return array<int, string> Absolute filesystem paths (may not exist yet)
     */
    public static function resolve(array $channelNames): array
    {
        $paths = [];

        foreach ($channelNames as $name) {
            if (! is_string($name) || $name === '') {
                continue;
            }

            $cfg = config("logging.channels.{$name}");

            if (! is_array($cfg)) {
                continue;
            }
            
            foreach (self::pathsFromChannel($cfg) as $p) {
                $paths[$p] = true;
            }
        }

        return array_keys($paths);
    }

    /** @param  array<string, mixed>  $cfg */
    private static function pathsFromChannel(array $cfg): array
    {
        $driver = (string) ($cfg['driver'] ?? '');

        return match ($driver) {
            'single' => self::pathsForSingleDriver($cfg),
            'daily' => self::pathsForDailyDriver($cfg),
            'stack' => self::pathsForStackDriver($cfg),
            default => [],
        };
    }

    /** @param  array<string, mixed>  $cfg */
    private static function pathsForSingleDriver(array $cfg): array
    {
        $path = isset($cfg['path']) ? (string) $cfg['path'] : '';

        return $path !== '' ? [$path] : [];
    }

    /** @param  array<string, mixed>  $cfg */
    private static function pathsForDailyDriver(array $cfg): array
    {
        $path = isset($cfg['path']) ? (string) $cfg['path'] : '';

        if ($path === '') {
            return [];
        }

        $dir = dirname($path);

        $base = pathinfo($path, PATHINFO_FILENAME);

        $date = Carbon::now()->format('Y-m-d');

        return ["{$dir}/{$base}-{$date}.log"];
    }

    /** @param  array<string, mixed>  $cfg */
    private static function pathsForStackDriver(array $cfg): array
    {
        $out = [];

        $nested = $cfg['channels'] ?? [];

        if (! is_array($nested)) {
            return [];
        }

        foreach ($nested as $child) {

            if (! is_string($child) || $child === '') {
                continue;
            }

            $sub = config("logging.channels.{$child}");

            if (! is_array($sub)) {
                continue;
            }

            foreach (self::pathsFromChannel($sub) as $p) {
                $out[] = $p;
            }

        }

        return $out;
    }
}