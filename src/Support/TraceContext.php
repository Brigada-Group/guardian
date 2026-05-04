<?php

namespace Brigada\Guardian\Support;

use Illuminate\Support\Facades\Context;

class TraceContext
{
    public const KEY = 'trace_id';

    private const TRACE_ID_REGEX = '/^[0-9a-f]{32}$/';

    public static function start(?string $traceparent = null): string
    {
        $traceId = self::parseTraceparent($traceparent) ?? self::generate();

        Context::add(self::KEY, $traceId);

        return $traceId;
    }

    public static function current(): ?string
    {
        $traceId = Context::get(self::KEY);

        if (! is_string($traceId) || ! self::isValid($traceId)) {
            return null;
        }

        return $traceId;
    }

    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }

    public static function parseTraceparent(?string $header): ?string
    {
        if (! is_string($header) || $header === '') {
            return null;
        }

        $parts = explode('-', trim($header));

        if (count($parts) < 4) {
            return null;
        }

        [$version, $traceId] = $parts;

        if (! preg_match('/^[0-9a-f]{2}$/', $version) || $version === 'ff') {
            return null;
        }

        if (! self::isValid($traceId) || $traceId === str_repeat('0', 32)) {
            return null;
        }

        return $traceId;
    }

    public static function isValid(string $traceId): bool
    {
        return (bool) preg_match(self::TRACE_ID_REGEX, $traceId);
    }
}