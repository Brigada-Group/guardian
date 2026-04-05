<?php

namespace Brigada\Guardian\Tests\Unit\Security;

use Brigada\Guardian\Security\HeaderFilter;
use Brigada\Guardian\Tests\TestCase;

class HeaderFilterTest extends TestCase
{
    public function test_allows_safe_headers(): void
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0',
            'Accept' => 'text/html',
            'Referer' => 'https://example.com',
            'Content-Type' => 'application/json',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertCount(4, $result);
        $this->assertEquals('Mozilla/5.0', $result['User-Agent']);
    }

    public function test_strips_authorization_header(): void
    {
        $headers = [
            'User-Agent' => 'Mozilla/5.0',
            'Authorization' => 'Bearer sk-secret-token',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertArrayNotHasKey('Authorization', $result);
        $this->assertArrayHasKey('User-Agent', $result);
    }

    public function test_strips_cookie_header(): void
    {
        $headers = [
            'Cookie' => 'session=abc123; token=xyz',
            'Accept' => 'text/html',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertArrayNotHasKey('Cookie', $result);
    }

    public function test_strips_csrf_and_api_key_headers(): void
    {
        $headers = [
            'X-CSRF-Token' => 'abc123',
            'X-API-Key' => 'secret-key',
            'Accept' => 'text/html',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertArrayNotHasKey('X-CSRF-Token', $result);
        $this->assertArrayNotHasKey('X-API-Key', $result);
    }

    public function test_case_insensitive_matching(): void
    {
        $headers = [
            'user-agent' => 'Mozilla/5.0',
            'authorization' => 'Bearer token',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertCount(1, $result);
    }

    public function test_uses_config_safe_headers(): void
    {
        config(['guardian.security.safe_headers' => ['X-Custom']]);
        $headers = [
            'User-Agent' => 'Mozilla/5.0',
            'X-Custom' => 'allowed',
        ];
        $result = HeaderFilter::filter($headers);
        $this->assertCount(1, $result);
        $this->assertArrayHasKey('X-Custom', $result);
    }
}
