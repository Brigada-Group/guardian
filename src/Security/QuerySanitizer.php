<?php

namespace Brigada\Guardian\Security;

class QuerySanitizer
{
    public static function sanitize(string $sql): string
    {
        if ($sql === '') {
            return '';
        }

        if (! config('guardian.security.sanitize_sql', true)) {
            return $sql;
        }

        // Redact single-quoted string literals
        $sql = preg_replace("/'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'/", "'[REDACTED]'", $sql);

        // Redact double-quoted string literals (MySQL)
        $sql = preg_replace('/"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"/', '"[REDACTED]"', $sql);

        // Redact numeric literals in conditions (after comparison operators)
        $sql = preg_replace('/((?:=|>|<|>=|<=|<>|!=)\s*)\d+(\.\d+)?/', '$1?', $sql);

        return $sql;
    }
}
