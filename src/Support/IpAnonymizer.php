<?php

namespace Brigada\Guardian\Support;

class IpAnonymizer
{
    public static function anonymize(?string $ip): ?string
    {
        if ($ip === null) {
            return null;
        }

        if (! config('guardian.security.anonymize_ip', false)) {
            return $ip;
        }

        // IPv4: zero the last octet
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.0', $ip);
        }

        // IPv6: zero the last 80 bits (keep first 48 bits / 3 groups)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($ip);
            for ($i = 6; $i < 16; $i++) {
                $packed[$i] = "\0";
            }
            return inet_ntop($packed);
        }

        return $ip;
    }
}
