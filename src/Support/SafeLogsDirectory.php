<?php

namespace Brigada\Guardian\Support;

final class SafeLogsDirectory
{
    public static function allowedRoot(): string
    {
        return rtrim(realpath(storage_path('logs')) ?: storage_path('logs'), DIRECTORY_SEPARATOR);
    }

    /**
     * @return non-empty-string|null Real path readable by PHP, constrained under storage/logs
     */
    public static function sanitizeExistingFile(string $path): ?string
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $resolved = realpath($path);
        if ($resolved === false) {
            return null;
        }

        $rootReal = realpath(storage_path('logs'));

        if ($rootReal === false) {
            return null;
        }

        $needle = $rootReal.DIRECTORY_SEPARATOR;

        if ($resolved !== $rootReal && ! str_starts_with($resolved.DIRECTORY_SEPARATOR, $needle)) {
            return null;
        }

        return $resolved;
    }
}
