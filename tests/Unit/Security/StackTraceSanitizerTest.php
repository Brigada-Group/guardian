<?php

namespace Brigada\Guardian\Tests\Unit\Security;

use Brigada\Guardian\Security\StackTraceSanitizer;
use Brigada\Guardian\Tests\TestCase;

class StackTraceSanitizerTest extends TestCase
{
    public function test_strips_base_path_from_file_paths(): void
    {
        $trace = base_path() . '/app/Http/Controllers/UserController.php:42';
        $result = StackTraceSanitizer::sanitize($trace);
        $this->assertStringNotContainsString(base_path(), $result);
        $this->assertStringContainsString('app/Http/Controllers/UserController.php:42', $result);
    }

    public function test_redacts_secret_patterns_in_message(): void
    {
        $message = 'Connection failed: password=MyS3cretP@ss host=db.example.com';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertStringNotContainsString('MyS3cretP@ss', $result);
        $this->assertStringContainsString('[REDACTED]', $result);
    }

    public function test_redacts_token_patterns(): void
    {
        $message = 'API error: token=abc123xyz authorization=Bearer sk-12345';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertStringNotContainsString('abc123xyz', $result);
        $this->assertStringNotContainsString('sk-12345', $result);
    }

    public function test_redacts_key_patterns(): void
    {
        $message = 'Config: api_key=AKIAIOSFODNN7EXAMPLE secret_key=wJalrXUtnFEMI/K7MDENG';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertStringNotContainsString('AKIAIOSFODNN7EXAMPLE', $result);
        $this->assertStringNotContainsString('wJalrXUtnFEMI/K7MDENG', $result);
    }

    public function test_handles_empty_string(): void
    {
        $this->assertEquals('', StackTraceSanitizer::sanitize(''));
    }

    public function test_preserves_non_sensitive_content(): void
    {
        $message = 'Division by zero in calculate() at line 42';
        $result = StackTraceSanitizer::sanitize($message);
        $this->assertEquals($message, $result);
    }
}
