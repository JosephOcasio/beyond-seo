<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

/**
 * EnvironmentSecurityValidator
 *
 * Validates that the application environment is configured securely.
 * Used in CI/CD and startup to enforce mandatory security settings.
 *
 * @since 1.0.0
 */
class EnvironmentSecurityValidator
{
    /**
     * Validation errors collected during checks
     * @var array<string>
     */
    private array $errors = [];

    /**
     * Validation warnings
     * @var array<string>
     */
    private array $warnings = [];

    /**
     * Run all security validation checks
     */
    public function validate(): bool
    {
        $this->validateDebugMode();
        $this->validateRequiredCredentials();
        $this->validateSecureDefaults();

        return empty($this->errors);
    }

    /**
     * Validate APP_DEBUG is false in production
     */
    private function validateDebugMode(): void
    {
        $appDebug = getenv('APP_DEBUG');
        $appEnv = getenv('APP_ENV') ?: 'dev';

        // In production, debug mode MUST be false
        if ($appEnv === 'prod' || $appEnv === 'production') {
            if ($appDebug === 'true' || $appDebug === '1') {
                $this->addError(
                    'APP_DEBUG must be false in production environment. ' .
                    'Set APP_DEBUG=false to prevent error page information disclosure.'
                );
            }
        } else {
            // In dev/test, debug can be enabled but log a warning
            if ($appDebug === 'true' || $appDebug === '1') {
                $this->addWarning(
                    'APP_DEBUG is enabled in ' . $appEnv . ' environment. ' .
                    'Ensure this is intentional and debug mode is disabled in production.'
                );
            }
        }
    }

    /**
     * Validate required environment credentials are set
     */
    private function validateRequiredCredentials(): void
    {
        $requiredVars = [
            'APP_ENV' => 'Application environment (dev, test, prod)',
            'APP_DEBUG' => 'Debug mode flag',
        ];

        foreach ($requiredVars as $var => $description) {
            if (empty(getenv($var))) {
                $this->addError("Required environment variable missing: {$var} ({$description})");
            }
        }

        // Check if running in production and additional vars are needed
        if ((getenv('APP_ENV') ?: 'dev') === 'prod') {
            $prodRequiredVars = [
                'DB_PASSWORD' => 'Database password',
                'DB_USER' => 'Database user',
            ];

            foreach ($prodRequiredVars as $var => $description) {
                if (empty(getenv($var))) {
                    $this->addError(
                        "Required production environment variable missing: {$var} ({$description}). " .
                        'Inject credentials via environment, not .env file.'
                    );
                }
            }
        }
    }

    /**
     * Validate secure default configurations
     */
    private function validateSecureDefaults(): void
    {
        // Check .env file doesn't contain actual credentials
        $envPath = dirname(__DIR__, 4) . '/.env';
        if (file_exists($envPath)) {
            $envContent = file_get_contents($envPath);

            // Pattern: password or key with actual value (not placeholder)
            if (preg_match('/DB_PASSWORD=(?!%DB_PASSWORD%|"\$|\'|\s*$)/', $envContent)) {
                $this->addError(
                    'Hardcoded DB_PASSWORD found in .env file. ' .
                    'Use placeholder format DB_PASSWORD="%DB_PASSWORD%" and inject at runtime.'
                );
            }

            if (preg_match('/API.*PASSWORD=(?!%|"\$|\'|\s*$)/', $envContent)) {
                $this->addError(
                    'Hardcoded API credential found in .env file. ' .
                    'Use environment variable placeholders and inject at runtime.'
                );
            }
        }
    }

    /**
     * Add validation error
     */
    private function addError(string $message): void
    {
        $this->errors[] = $message;
    }

    /**
     * Add validation warning
     */
    private function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    /**
     * Get all collected errors
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all collected warnings
     * @return array<string>
     */
    public function getWarnings(): array
    {
        return $this->warnings;
    }

    /**
     * Get formatted validation report
     */
    public function getReport(): string
    {
        $report = "Security Environment Validation Report\n";
        $report .= "========================================\n\n";

        if (empty($this->errors) && empty($this->warnings)) {
            $report .= "✅ All checks passed.\n";
            return $report;
        }

        if (!empty($this->errors)) {
            $report .= "❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                $report .= "  • " . $error . "\n";
            }
            $report .= "\n";
        }

        if (!empty($this->warnings)) {
            $report .= "⚠️  WARNINGS (" . count($this->warnings) . "):\n";
            foreach ($this->warnings as $warning) {
                $report .= "  • " . $warning . "\n";
            }
            $report .= "\n";
        }

        return $report;
    }
}
