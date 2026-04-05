<?php

namespace Brigada\Guardian\Tests\Unit\Support;

use Brigada\Guardian\Support\IpAnonymizer;
use Brigada\Guardian\Tests\TestCase;

class IpAnonymizerTest extends TestCase
{
    public function test_anonymizes_ipv4(): void
    {
        config(['guardian.security.anonymize_ip' => true]);
        $this->assertEquals('192.168.1.0', IpAnonymizer::anonymize('192.168.1.42'));
    }

    public function test_anonymizes_ipv6(): void
    {
        config(['guardian.security.anonymize_ip' => true]);
        $result = IpAnonymizer::anonymize('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
        $this->assertStringEndsWith('::', $result);
        $this->assertStringStartsWith('2001:', $result);
    }

    public function test_returns_null_for_null(): void
    {
        $this->assertNull(IpAnonymizer::anonymize(null));
    }

    public function test_returns_original_when_disabled(): void
    {
        config(['guardian.security.anonymize_ip' => false]);
        $this->assertEquals('192.168.1.42', IpAnonymizer::anonymize('192.168.1.42'));
    }

    public function test_handles_localhost(): void
    {
        config(['guardian.security.anonymize_ip' => true]);
        $this->assertEquals('127.0.0.0', IpAnonymizer::anonymize('127.0.0.1'));
    }
}
