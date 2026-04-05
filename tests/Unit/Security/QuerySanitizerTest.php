<?php

namespace Brigada\Guardian\Tests\Unit\Security;

use Brigada\Guardian\Security\QuerySanitizer;
use Brigada\Guardian\Tests\TestCase;

class QuerySanitizerTest extends TestCase
{
    public function test_redacts_string_literals_in_where_clause(): void
    {
        $sql = "select * from users where email = 'john@example.com' and status = 'active'";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringNotContainsString('john@example.com', $result);
        $this->assertStringNotContainsString('active', $result);
        $this->assertStringContainsString('[REDACTED]', $result);
        $this->assertStringContainsString('select * from users where email', $result);
    }

    public function test_redacts_numeric_values_in_where_clause(): void
    {
        $sql = "select * from orders where id = 12345 and total > 99.99";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringNotContainsString('12345', $result);
        $this->assertStringContainsString('?', $result);
    }

    public function test_preserves_table_and_column_names(): void
    {
        $sql = "select id, name from users where id = 1";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringContainsString('select id, name from users', $result);
    }

    public function test_redacts_insert_values(): void
    {
        $sql = "insert into users (email, password) values ('user@test.com', 'secret123')";
        $result = QuerySanitizer::sanitize($sql);
        $this->assertStringNotContainsString('user@test.com', $result);
        $this->assertStringNotContainsString('secret123', $result);
    }

    public function test_handles_null_and_empty_strings(): void
    {
        $this->assertEquals('', QuerySanitizer::sanitize(''));
    }

    public function test_disabled_via_config_returns_original(): void
    {
        config(['guardian.security.sanitize_sql' => false]);
        $sql = "select * from users where email = 'john@example.com'";
        $this->assertEquals($sql, QuerySanitizer::sanitize($sql));
    }
}
