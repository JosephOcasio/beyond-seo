<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Services;

use PHPUnit\Framework\TestCase;
use App\Infrastructure\Services\EnvironmentSecurityValidator;

/**
 * EnvironmentSecurityValidatorTest
 *
 * Tests for environment security validation
 */
class EnvironmentSecurityValidatorTest extends TestCase
{
    private EnvironmentSecurityValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new EnvironmentSecurityValidator();
    }

    /**
     * @test
     */
    public function testValidatorInitializes(): void
    {
        $this->assertInstanceOf(EnvironmentSecurityValidator::class, $this->validator);
    }

    /**
     * @test
     */
    public function testValidateReturnsBoolean(): void
    {
        $result = $this->validator->validate();
        $this->assertIsBool($result);
    }

    /**
     * @test
     */
    public function testGetErrorsReturnsArray(): void
    {
        $this->validator->validate();
        $errors = $this->validator->getErrors();
        $this->assertIsArray($errors);
    }

    /**
     * @test
     */
    public function testGetWarningsReturnsArray(): void
    {
        $this->validator->validate();
        $warnings = $this->validator->getWarnings();
        $this->assertIsArray($warnings);
    }

    /**
     * @test
     */
    public function testGetReportReturnsString(): void
    {
        $this->validator->validate();
        $report = $this->validator->getReport();
        $this->assertIsString($report);
        $this->assertStringContainsString('Security Environment Validation', $report);
    }
}
