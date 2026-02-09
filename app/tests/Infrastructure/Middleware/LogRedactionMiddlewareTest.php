<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Middleware;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Middleware\LogRedactionMiddleware;

/**
 * LogRedactionMiddlewareTest
 *
 * Tests for log redaction middleware
 */
class LogRedactionMiddlewareTest extends TestCase
{
    /**
     * @test
     */
    public function testRedactArrayDataRedactsPasswordField(): void
    {
        $data = [
            'username' => 'admin',
            'password' => 'secret123',
        ];

        $redacted = LogRedactionMiddleware::redactArrayData($data);

        $this->assertEquals('admin', $redacted['username']);
        $this->assertEquals('[REDACTED]', $redacted['password']);
    }

    /**
     * @test
     */
    public function testRedactArrayDataRedactsMultipleSensitiveFields(): void
    {
        $data = [
            'username' => 'user@example.com',
            'password' => 'secret',
            'api_key' => 'abc123def456',
            'token' => 'jwt_token_xyz',
            'refresh_token' => 'refresh_xyz',
        ];

        $redacted = LogRedactionMiddleware::redactArrayData($data);

        $this->assertEquals('[REDACTED]', $redacted['password']);
        $this->assertEquals('[REDACTED]', $redacted['api_key']);
        $this->assertEquals('[REDACTED]', $redacted['token']);
        $this->assertEquals('[REDACTED]', $redacted['refresh_token']);
    }

    /**
     * @test
     */
    public function testRedactArrayDataHandlesNestedArrays(): void
    {
        $data = [
            'user' => [
                'name' => 'John',
                'password' => 'secret123',
            ],
            'api' => [
                'endpoint' => 'https://api.example.com',
                'api_key' => 'key123',
            ],
        ];

        $redacted = LogRedactionMiddleware::redactArrayData($data);

        $this->assertEquals('John', $redacted['user']['name']);
        $this->assertEquals('[REDACTED]', $redacted['user']['password']);
        $this->assertEquals('https://api.example.com', $redacted['api']['endpoint']);
        $this->assertEquals('[REDACTED]', $redacted['api']['api_key']);
    }

    /**
     * @test
     */
    public function testRedactStringDataRedactsBearerToken(): void
    {
        $data = 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9';

        $redacted = LogRedactionMiddleware::redactStringData($data);

        $this->assertStringContainsString('Bearer [REDACTED]', $redacted);
        $this->assertStringNotContainsString('eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9', $redacted);
    }

    /**
     * @test
     */
    public function testRedactStringDataRedactsJsonCredentials(): void
    {
        $data = '{"username": "admin", "password": "secret123"}';

        $redacted = LogRedactionMiddleware::redactStringData($data);

        $this->assertStringContainsString('"password": "[REDACTED]"', $redacted);
        $this->assertStringNotContainsString('secret123', $redacted);
    }

    /**
     * @test
     */
    public function testRedactStringDataRedactsFormData(): void
    {
        $data = 'username=admin&password=secret123&email=test@example.com';

        $redacted = LogRedactionMiddleware::redactStringData($data);

        $this->assertStringContainsString('password=[REDACTED]', $redacted);
        $this->assertStringNotContainsString('secret123', $redacted);
    }

    /**
     * @test
     */
    public function testRedactArrayDataIsCaseInsensitive(): void
    {
        $data = [
            'PASSWORD' => 'secret',
            'Password' => 'secret2',
            'API_Key' => 'key123',
        ];

        $redacted = LogRedactionMiddleware::redactArrayData($data);

        $this->assertEquals('[REDACTED]', $redacted['PASSWORD']);
        $this->assertEquals('[REDACTED]', $redacted['Password']);
        $this->assertEquals('[REDACTED]', $redacted['API_Key']);
    }
}
