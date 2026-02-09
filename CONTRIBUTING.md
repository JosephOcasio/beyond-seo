# Contributing to BeyondSEO

Thank you for your interest in contributing to BeyondSEO! This guide explains how to participate.

## Code of Conduct

Be respectful, inclusive, and professional. We're committed to providing a welcoming environment.

## Getting Started

### 1. Fork & Clone

```bash
git clone https://github.com/rankingcoach/beyond-seo.git
cd beyond-seo
```

### 2. Create a Branch

```bash
git checkout -b feature/your-feature-name
# or
git checkout -b fix/issue-number
```

**Branch naming:** `feature/*`, `fix/*`, `docs/*`, `test/*`

### 3. Set Up Environment

```bash
# Install dependencies
composer install

# Configure environment
cp app/.env.example app/.env
# Edit app/.env with your settings
```

## Making Changes

### Code Standards

- **PHP:** PSR-12 coding standard
- **PHP Version:** 8.0+
- **Type Hints:** Always use strict types
- **Docblocks:** PHPDoc format for all classes/methods

```php
<?php
declare(strict_types=1);

/**
 * ClassName
 * 
 * Description of the class.
 *
 * @since 1.1.6
 */
class ClassName
{
    /**
     * Do something important
     * 
     * @param string $param Description
     * @return bool Success status
     */
    public function doSomething(string $param): bool
    {
        // Implementation
    }
}
```

### Best Practices

- ✅ Keep commits focused and atomic
- ✅ Write meaningful commit messages
- ✅ Add tests for new features
- ✅ Update documentation
- ✅ Handle errors gracefully
- ✅ Follow DRY principle
- ✅ Use type hints everywhere

### Avoid

- ❌ Direct WordPress queries without abstraction
- ❌ Hardcoded secrets or credentials
- ❌ Silently failing error handling
- ❌ Breaking existing APIs
- ❌ Large monolithic commits

## Testing Requirements

All changes must include tests.

### Add Tests

```bash
# Create test file in app/tests/
# Test filename: {ClassNameToTest}Test.php

# Write tests using PHPUnit
vendor/bin/phpunit
```

### Minimum Requirements

- ✅ 80%+ code coverage for new code
- ✅ All tests passing: `vendor/bin/phpunit`
- ✅ Static analysis passing: `vendor/bin/phpstan analyse app/src --level=max`
- ✅ Code style passing: `vendor/bin/phpcs --standard=PSR12 app/src`

### Run All Checks

```bash
# Tests
vendor/bin/phpunit

# Coverage
vendor/bin/phpunit --coverage-html coverage/

# Static analysis
vendor/bin/phpstan analyse app/src --level=max

# Code style
vendor/bin/phpcs --standard=PSR12 app/src
vendor/bin/phpcbf --standard=PSR12 app/src  # Auto-fix

# Syntax validation
find app/src -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Commit Guidelines

### Writing Good Commits

```bash
# Good commit message format:
# [type]: Brief summary (max 50 chars)
#
# Detailed explanation (wrap at 72 chars)
# - What changed
# - Why it changed
# - Any breaking changes

git commit -m "feat: Add REST API endpoint for settings

Implement /api/settings endpoints for CRUD operations on plugin settings.
Includes authentication, rate limiting, and comprehensive error handling.

- GET /api/settings - retrieve all settings
- POST /api/settings/{key} - create/update setting
- DELETE /api/settings/{key} - delete setting
- POST /api/settings/batch - batch updates

Closes #123"
```

### Types

- `feat:` New feature
- `fix:` Bug fix
- `docs:` Documentation
- `test:` Tests
- `refactor:` Code refactoring
- `perf:` Performance improvement
- `chore:` Build, dependencies, etc.

## Pull Request Process

### Before Submitting

1. **Update from main:**
   ```bash
   git fetch origin
   git rebase origin/main
   ```

2. **Run full test suite:**
   ```bash
   vendor/bin/phpunit
   vendor/bin/phpstan analyse app/src --level=max
   vendor/bin/phpcs --standard=PSR12 app/src
   ```

3. **Update documentation:**
   - README.md if behavior changes
   - API_DOCUMENTATION.md for API changes
   - TESTING.md for test changes
   - CHANGELOG section in relevant docs

### Create PR

1. Push to your fork: `git push origin feature/your-feature`
2. Create Pull Request on GitHub
3. Fill out PR template completely
4. Link related issues: "Closes #123"

### PR Template

```markdown
## Description
Brief description of changes

## Type
- [ ] Feature
- [ ] Bug Fix
- [ ] Documentation
- [ ] Performance

## Testing
- [ ] Added tests
- [ ] Tests passing
- [ ] Coverage maintained (80%+)

## Checklist
- [ ] Code follows PSR-12
- [ ] Documentation updated
- [ ] No breaking changes
- [ ] Security issues addressed

## Related Issues
Closes #123
```

### PR Review Process

- ✅ CI/CD checks must pass
- ✅ Code review required (1 approval minimum)
- ✅ Tests coverage maintained
- ✅ No merge conflicts
- ✅ Squash commits before merge

## Adding New Features

### Feature Checklist

- [ ] Create branch: `git checkout -b feature/feature-name`
- [ ] Write tests first (TDD)
- [ ] Implement feature
- [ ] Update documentation
- [ ] Add to changelog
- [ ] Run full test suite
- [ ] Create pull request

### New REST Endpoint

1. **Create controller** in `app/src/Presentation/Http/Controllers/Api/`
2. **Add routes** to `app/config/routes/api.yaml`
3. **Write tests** in `app/tests/`
4. **Document** in `API_DOCUMENTATION.md`
5. **Examples** in cURL, PHP, JavaScript

### New Admin Page

1. **Create class** in `inc/Core/Admin/`
2. **Implement WordPress hooks** (admin_menu, admin_init)
3. **Add tests** for validation
4. **Style with CSS** in `assets/css/`
5. **Document** in README.md

### New Setting

1. **Add to SettingsPage.php** (register, render field)
2. **Add to API endpoint**
3. **Update database option key** (convention: `beyond_seo_*`)
4. **Add validation/sanitization**
5. **Document** setting purpose

## Security

### Security Issues

Please email security@rankingcoach.com instead of creating public issues.

### Security Checklist

- [ ] No hardcoded credentials
- [ ] Sensitive data properly sanitized
- [ ] Input validation on all user data
- [ ] Output escaping for display
- [ ] Nonce verification for forms
- [ ] Capability checks for admin functions
- [ ] Rate limiting for API endpoints

### Credential Guidelines

✅ DO:
```php
$password = getenv('DB_PASSWORD');  // From environment
$secret = Config::getEnv('API_KEY');
update_option('key', $encrypted_value);
```

❌ DON'T:
```php
$password = 'hardcoded123';  // Hardcoded!
define('API_KEY', 'secret');  // In code!
echo $password;  // Logs!
```

## Documentation

### Update Documentation

- **README.md** - Feature overview, installation, setup
- **API_DOCUMENTATION.md** - REST API reference
- **TESTING.md** - Testing guidelines
- **SECURITY_CONFIG.md** - Security setup
- **Code comments** - Complex logic explanation

### Documentation Format

```markdown
## Section Title

Paragraph explaining the topic.

### Subsection

- Point 1
- Point 2
- Point 3

**Example:**
```php
// Code example
```

### Important Notes

- [ ] Grammar and spelling checked
- [ ] Code examples tested
- [ ] Links are valid
- [ ] Screenshots updated if needed
```

## Release Process

Releases follow [Semantic Versioning](https://semver.org/).

### Version Format
`MAJOR.MINOR.PATCH` (e.g., 1.2.3)

- **MAJOR:** Breaking changes
- **MINOR:** New features (backwards compatible)
- **PATCH:** Bug fixes

### Release Checklist

Maintainers only:
1. Update version in `beyond-seo.php`
2. Update CHANGELOG
3. Tag commit: `git tag v1.2.3`
4. Push tags: `git push origin --tags`
5. Create GitHub Release
6. Announce on website

## Getting Help

### Documentation

- [README.md](README.md) - Installation, setup, features
- [API_DOCUMENTATION.md](API_DOCUMENTATION.md) - REST API
- [TESTING.md](TESTING.md) - Testing guide
- [SECURITY_CONFIG.md](SECURITY_CONFIG.md) - Security

### Communication

- 💬 GitHub Issues - Bug reports, feature requests
- 📧 Email - security@rankingcoach.com (security issues)
- 🌐 Website - https://www.rankingcoach.com

## License

By contributing, you agree that your code will be licensed under GPL v2 or later.

## Contributors

Thanks to all contributors! See [CONTRIBUTORS.md](CONTRIBUTORS.md).

---

**Happy contributing!** 🚀

Questions? Open an issue or email us.
