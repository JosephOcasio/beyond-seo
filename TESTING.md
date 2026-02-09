# Testing Guide

Complete guide for testing the BeyondSEO plugin.

## Unit Tests

### Setup

```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Verify installation
vendor/bin/phpunit --version
```

### Run Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run with verbose output
vendor/bin/phpunit -v

# Run specific test file
vendor/bin/phpunit app/tests/Infrastructure/Services/EnvironmentSecurityValidatorTest.php

# Run specific test method
vendor/bin/phpunit --filter testValidatorInitializes

# Generate HTML coverage report
vendor/bin/phpunit --coverage-html coverage/

# Generate text coverage report
vendor/bin/phpunit --coverage-text
```

### Test Files

#### EnvironmentSecurityValidatorTest

Tests environment validation logic:

```bash
vendor/bin/phpunit app/tests/Infrastructure/Services/EnvironmentSecurityValidatorTest.php
```

Tests included:
- ✅ Validator initializes correctly
- ✅ `validate()` returns boolean
- ✅ `getErrors()` returns array of errors
- ✅ `getWarnings()` returns array of warnings
- ✅ `getReport()` returns formatted string

#### LogRedactionMiddlewareTest

Tests sensitive data redaction:

```bash
vendor/bin/phpunit app/tests/Infrastructure/Middleware/LogRedactionMiddlewareTest.php
```

Tests included:
- ✅ Redacts password fields
- ✅ Redacts API keys, tokens
- ✅ Handles nested arrays
- ✅ Redacts Bearer tokens from strings
- ✅ Redacts JSON credentials
- ✅ Redacts form data
- ✅ Case-insensitive field matching

## Integration Tests

### Manual Testing Checklist

#### Settings Admin Page

- [ ] Navigate to **Tools > BeyondSEO Settings**
- [ ] Tab switching works (General, SEO, API)
- [ ] Settings display current values
- [ ] Can update General settings
- [ ] Can update SEO settings
- [ ] Can update API settings
- [ ] Settings persist after refresh
- [ ] Admin notification shows on successful save

#### REST API Endpoints

```bash
# Test with cURL

# 1. Get all settings (requires authentication)
curl -u admin:password https://example.com/wp-json/wp/v2/users/me
curl -X GET https://example.com/api/settings \
  -H "Authorization: Bearer YOUR_TOKEN"

# 2. Get specific setting
curl -X GET https://example.com/api/settings/cache_enabled \
  -H "Authorization: Bearer YOUR_TOKEN"

# 3. Update setting
curl -X POST https://example.com/api/settings/cache_ttl \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"value": 3600}'

# 4. Batch update
curl -X POST https://example.com/api/settings/batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": {
      "cache_enabled": true,
      "cache_ttl": 7200
    }
  }'

# 5. Delete setting
curl -X DELETE https://example.com/api/settings/cache_ttl \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## Security Testing

### Credential Leak Scan

```bash
# Check for hardcoded credentials
grep -r "password\|secret\|api.?_?key" app/src --include="*.php" | grep -v "getEnv\|Config::"

# Check .env file
grep -E "PASSWORD|TOKEN|SECRET" app/.env | grep -v "%"
```

### Environment Validation

```bash
# Run validation manually
php -r "
require 'app/tests/bootstrap.php';
\$validator = new App\Infrastructure\Services\EnvironmentSecurityValidator();
\$validator->validate();
echo \$validator->getReport();
"
```

### Log Redaction Test

```php
<?php
require 'app/tests/bootstrap.php';

use App\Infrastructure\Middleware\LogRedactionMiddleware;

$data = [
    'username' => 'admin',
    'password' => 'secret123',
    'api_key' => 'abc123',
];

$redacted = LogRedactionMiddleware::redactArrayData($data);
print_r($redacted);
// Output:
// Array
// (
//     [username] => admin
//     [password] => [REDACTED]
//     [api_key] => [REDACTED]
// )
?>
```

## Performance Testing

### Cache Effectiveness

```bash
# Measure response time with caching enabled
time curl https://example.com/api/settings

# Disable cache and measure again
curl -X POST https://example.com/api/settings/cache_enabled \
  -H "Authorization: Bearer TOKEN" \
  -d '{"value": false}'

time curl https://example.com/api/settings

# Re-enable
curl -X POST https://example.com/api/settings/cache_enabled \
  -H "Authorization: Bearer TOKEN" \
  -d '{"value": true}'
```

### Load Testing

```bash
# Install Apache Bench
brew install httpd  # macOS
apt-get install apache2-utils  # Linux

# Run load test
ab -n 1000 -c 10 https://example.com/api/settings
```

## CI/CD Testing

### Local GitHub Actions Simulation

```bash
# Install act (GitHub Actions local runner)
brew install act

# Run workflows locally
act push --workflows .github/workflows/security-validation.yml
act push --workflows .github/workflows/static-analysis.yml
```

### Manual CI Checks

```bash
# Security validation
grep -r "DB_PASSWORD=(?!%)" app/ --include="*.env" --include="*.php"
grep -r "APP_DEBUG.*true" app/config --include="*.php"

# PHP syntax validation
find app/src -name '*.php' -print0 | xargs -0 -n1 php -l

# Code style (PSR-12)
vendor/bin/phpcs --standard=PSR12 app/src/

# Static analysis
vendor/bin/phpstan analyse app/src --level=max
```

## Test Coverage Goals

| Module | Coverage | Tests |
|--------|----------|-------|
| Services | 85%+ | 10+ |
| Middleware | 90%+ | 8+ |
| Controllers | 80%+ | 15+ |
| Overall | 85%+ | 50+ |

## Debugging Tests

### Enable Verbose Output

```bash
vendor/bin/phpunit --verbose --debug
```

### Single Test with Output

```bash
vendor/bin/phpunit --filter testNameHere -v
```

### Debug Information

Add to test:
```php
public function testExample() {
    $this->assertTrue(true);
}
```

Use `--display-incomplete` flag for debugging:
```bash
vendor/bin/phpunit --display-incomplete
```

### Print Statements

```php
echo "Debug output\n";
fwrite(STDERR, "Error output\n");
var_dump($variable);
```

## Common Issues

### "Composer autoloader not found"

Solution:
```bash
composer install
```

### "PHPUnit command not found"

Solution:
```bash
vendor/bin/phpunit  # Use full path

# Or add to PATH
export PATH="./vendor/bin:$PATH"
phpunit
```

### Test fails with "requires WordPress"

Tests run in isolation. Some tests may need WordPress mocking:

```php
// Mock WordPress functions
if (!function_exists('get_option')) {
    function get_option($key, $default = false) {
        return $default;
    }
}
```

### Coverage report not generating

Ensure XDebug is installed:
```bash
php -m | grep xdebug

# Install if missing
pecl install xdebug
```

## Best Practices

1. **Isolation:** Each test should be independent
2. **Clarity:** Test names should describe what they test
3. **Coverage:** Aim for 80%+ code coverage
4. **Speed:** Unit tests should run in < 1 second
5. **Mocking:** Use mocks for external dependencies
6. **Assertions:** Use specific assertions (not just assertTrue)

## Continuous Integration

Tests run automatically on:
- Push to `main` or `develop` branches
- Pull requests

View results: **GitHub > Settings > Workflows > Test Results**

---

For additional testing frameworks and tools, see [README.md](README.md).
