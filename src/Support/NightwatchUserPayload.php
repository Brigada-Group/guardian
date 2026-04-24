<?php

namespace Brigada\Guardian\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves session user details for browser client-error ingest only.
 */
final class NightwatchUserPayload
{
    public static function resolve(): array
    {
        $user = self::firstAuthenticatedUser();

        if ($user === null) {
            return self::guest();
        }

        $payload = [
            'authenticated' => true,
            'context' => app()->runningInConsole() ? 'console' : 'http',
            'id' => (string) $user->getAuthIdentifier(),
            'name' => $user->name ?? null,
            'email' => $user->email ?? null,
        ];

        $extra = config(
            'guardian.client_errors.user_data_attributes',
            config('guardian.client_errors.user_payload_attributes', [])
        );

        foreach ($extra as $attribute) {
            if (! is_string($attribute) || $attribute === '') {
                continue;
            }
            if (! isset($user->{$attribute})) {
                continue;
            }
            $value = $user->{$attribute};
            if ($value instanceof \DateTimeInterface) {
                $payload[$attribute] = $value->format(\DateTimeInterface::ATOM);
            } elseif ($value instanceof \BackedEnum) {
                $payload[$attribute] = $value->value;
            } elseif ($value instanceof \UnitEnum) {
                $payload[$attribute] = $value->name;
            } elseif (is_scalar($value) || $value === null) {
                $payload[$attribute] = $value;
            } else {
                $payload[$attribute] = null;
            }
        }

        return $payload;
    }

    /**
     * Resolve the logged-in user for a web-session request.
     * Auth::check() only uses the default guard; many apps authenticate via `web` or `sanctum` while default is `api`.
     *
     * @return Authenticatable|null
     */
    private static function firstAuthenticatedUser(): ?Authenticatable
    {
        $guards = config('guardian.client_errors.auth_guards', ['web', 'sanctum']);
        if (! is_array($guards)) {
            $guards = ['web', 'sanctum'];
        }

        $registered = array_keys(config('auth.guards', []));

        foreach ($guards as $guard) {
            if (! is_string($guard) || $guard === '') {
                continue;
            }
            if (! in_array($guard, $registered, true)) {
                continue;
            }
            if (! Auth::guard($guard)->check()) {
                continue;
            }
            $candidate = Auth::guard($guard)->user();
            if ($candidate instanceof Authenticatable) {
                return $candidate;
            }
        }

        if (Auth::check()) {
            $fallback = Auth::user();

            return $fallback instanceof Authenticatable ? $fallback : null;
        }

        return null;
    }

    public static function guest(): array
    {
        return [
            'authenticated' => false,
            'context' => app()->runningInConsole() ? 'console' : 'http',
        ];
    }

    /**
     * Adds a single structured `user_data` object for Nightwatch (JSON column).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function merge(array $data): array
    {
        $data['user_data'] = self::resolve();

        return $data;
    }
}
