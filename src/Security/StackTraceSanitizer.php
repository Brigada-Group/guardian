<?php

namespace Brigada\Guardian\Security;

class StackTraceSanitizer
{
    private static array $sensitivePatterns = [
        'password',
        'passwd',
        'access_token',
        'api_key',
        'apikey',
        'secret_key',
        'token',
        'secret',
        'authorization',
        'credential',
    ];

    public static function sanitize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Strip base path from file references
        $basePath = base_path();
        if ($basePath) {
            $text = str_replace($basePath . '/', '', $text);
            $text = str_replace($basePath, '', $text);
        }

        // Redact Bearer tokens first (before key=value patterns)
        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer [REDACTED]', $text);

        // Redact values after sensitive key patterns (key=value or key: value)
        // Use underscore-aware boundary and capture remaining value tokens
        foreach (self::$sensitivePatterns as $pattern) {
            $text = preg_replace(
                '/(?<![a-zA-Z_])(' . preg_quote($pattern, '/') . ')\s*[=:]\s*\S+/i',
                '$1=[REDACTED]',
                $text
            );
        }

        return $text;
    }
}
