<?php

namespace Brigada\Guardian\Security;

class HeaderFilter
{
    public static function filter(array $headers): array
    {
        $allowed = array_map(
            'strtolower',
            config('guardian.security.safe_headers', ['User-Agent', 'Referer', 'Accept', 'Content-Type'])
        );

        $filtered = [];

        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $allowed, true)) {
                $filtered[$name] = $value;
            }
        }

        return $filtered;
    }
}
