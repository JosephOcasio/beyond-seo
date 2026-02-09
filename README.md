# BeyondSEO WordPress Plugin

Professional WordPress SEO plugin providing AI-powered optimization, Google rankings assistance, and online visibility tools.

## Features

- 🚀 **SEO Optimization** - Metadata management, sitemap generation, breadcrumbs
- 🔍 **XML Sitemaps** - Auto-generated sitemaps for search engines
- 📊 **Schema Markup** - Structured data for rich snippets
- 🎯 **Breadcrumbs** - Navigation path display
- 🔌 **REST API** - Programmatic access to plugin settings
- ⚙️ **Settings UI** - WordPress admin interface for configuration
- 🛡️ **Security** - Environment-based configuration, redaction middleware
- 📈 **Performance** - Built-in caching and optimization

## Requirements

- **PHP:** 8.0 or higher
- **WordPress:** 6.2 or higher
- **Composer:** For dependency management (optional but recommended)

## Installation

### 1. Download & Activate

1. Download the plugin ZIP file
2. Extract to `/wp-content/plugins/beyond-seo/`
3. Activate from WordPress admin: **Plugins > BeyondSEO**

### 2. Environment Setup

Copy the template `.env` file and configure credentials:

```bash
cp app/.env.example app/.env
```

Edit `app/.env` with your settings:

```dotenv
APP_ENV=prod
APP_DEBUG=false

# Database (use placeholders, inject at runtime)
DB_DEFAULT_CONNECTION_PASSWORD="%DB_PASSWORD%"
DB_DEFAULT_CONNECTION_USER="%DB_USER%"
```

### 3. Install Dependencies (Optional)

If using Composer:

```bash
composer install
```

## Configuration

### Via WordPress Admin

1. Go to **Tools > BeyondSEO Settings**
2. Configure:
   - **General:** Enable/disable plugin, cache settings
   - **SEO:** XML sitemap, breadcrumbs, schema markup
   - **API:** REST API access, rate limiting

### Via REST API

Get all settings:
```bash
curl -X GET https://example.com/api/settings \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Get specific setting:
```bash
curl -X GET https://example.com/api/settings/cache_enabled \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Update setting:
```bash
curl -X POST https://example.com/api/settings/cache_ttl \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"value": 7200}'
```

Batch update:
```bash
curl -X POST https://example.com/api/settings/batch \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": {
      "cache_enabled": true,
      "cache_ttl": 3600,
      "enable_xml_sitemap": true
    }
  }'
```

Delete setting:
```bash
curl -X DELETE https://example.com/api/settings/cache_ttl \
  -H "Authorization: Bearer YOUR_TOKEN"
```

## API Endpoints

### Settings Management

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/settings` | Get all settings |
| GET | `/api/settings/{key}` | Get specific setting |
| POST/PUT | `/api/settings/{key}` | Create/update setting |
| POST | `/api/settings/batch` | Batch update settings |
| DELETE | `/api/settings/{key}` | Delete setting |

**Authentication:** Requires admin user (WP REST API authentication)

**Rate Limiting:** Configured via admin settings (default: 1000 requests/hour)

## Plugin Settings Reference

### General Settings

- `enabled` (bool) - Enable/disable plugin functionality
- `debug_mode` (bool) - Enable debug logging (development only)
- `cache_enabled` (bool) - Enable response caching
- `cache_ttl` (int) - Cache time-to-live in seconds

### SEO Settings

- `enable_xml_sitemap` (bool) - Generate XML sitemaps
- `enable_breadcrumbs` (bool) - Show breadcrumb navigation
- `enable_schema_markup` (bool) - Add schema.org markup
- `default_separator` (string) - Title separator character

### API Settings

- `api_enabled` (bool) - Enable REST API endpoints
- `api_rate_limit` (int) - Requests per hour limit

## Testing

### Run Tests

```bash
# Install PHPUnit
composer require --dev phpunit/phpunit

# Run all tests
vendor/bin/phpunit

# Run specific test suite
vendor/bin/phpunit app/tests/Infrastructure/Services

# Generate coverage report
vendor/bin/phpunit --coverage-html coverage/
```

### Test Files

- `app/tests/Infrastructure/Services/EnvironmentSecurityValidatorTest.php`
- `app/tests/Infrastructure/Middleware/LogRedactionMiddlewareTest.php`

### Example Test

```php
$validator = new EnvironmentSecurityValidator();
$validator->validate();

if (!empty($validator->getErrors())) {
    echo "Environment validation failed:";
    foreach ($validator->getErrors() as $error) {
        echo "  • " . $error;
    }
}
```

## Security

### Credential Management

- All credentials loaded from environment variables
- No hardcoded secrets in source code
- Sensitive data excluded from logs

### Required Environment Variables

```bash
# Production deployment
export APP_ENV=prod
export APP_DEBUG=false
export DB_PASSWORD="<secure_password>"
export DB_USER="<db_admin>"
export API_ARGUS_MONOLITH_USER="<api_user>"
export API_ARGUS_MONOLITH_PASSWORD="<api_password>"
```

### CI/CD Validation

GitHub Actions workflows automatically:
- ✅ Scan for hardcoded credentials
- ✅ Verify APP_DEBUG is false in production
- ✅ Check PHP syntax
- ✅ Run static analysis (PHPStan, CodeSniffer)

See [SECURITY_CONFIG.md](SECURITY_CONFIG.md) for detailed security setup.

## Development

### Project Structure

```
beyond-seo/
├── app/                          # Symfony application
│   ├── src/
│   │   ├── Infrastructure/       # Services & middleware
│   │   ├── Presentation/         # Controllers & views
│   │   └── Domain/               # Business logic
│   ├── config/                   # Configuration
│   ├── tests/                    # PHPUnit tests
│   └── public/                   # Web root
├── inc/                          # WordPress plugin code
│   ├── Core/                     # Core functionality
│   │   ├── Admin/                # Admin pages & settings
│   │   ├── Managers/             # Feature managers
│   │   └── API/                  # Custom APIs
│   ├── Integrations/             # Third-party integrations
│   └── Traits/                   # Reusable traits
├── assets/                       # CSS, JS, images
├── languages/                    # Translation files
└── beyond-seo.php               # Main plugin file
```

### Adding Features

1. **New Setting:**
   - Add to `inc/Core/Admin/SettingsPage.php`
   - Register in WordPress settings
   - Add to REST API endpoint

2. **New REST Endpoint:**
   - Create controller in `app/src/Presentation/Http/Controllers/Api/`
   - Add routes to `app/config/routes/api.yaml`
   - Add tests in `app/tests/`

3. **New Admin Page:**
   - Create class extending WordPress settings API
   - Hook into `admin_menu` and `admin_init`
   - Enqueue styles/scripts via `admin_enqueue_scripts`

### Static Analysis

```bash
# Install tools
composer require --dev phpstan/phpstan squizlabs/php_codesniffer

# Run PHPStan
vendor/bin/phpstan analyse app/src --level=max

# Run CodeSniffer
vendor/bin/phpcs --standard=PSR12 app/src inc/
```

### Log Redaction

Sensitive data is automatically redacted from logs:
- Authorization headers
- API keys and tokens
- Passwords and credentials
- Bearer tokens

See [app/src/Infrastructure/Middleware/LogRedactionMiddleware.php](app/src/Infrastructure/Middleware/LogRedactionMiddleware.php).

## Troubleshooting

### Plugin Not Activating

Check error logs:
```bash
tail -f wp-content/debug.log
```

Ensure PHP 8.0+ is installed:
```bash
php --version
```

### Settings Not Saving

- Verify user has `manage_options` capability
- Check permission errors in error log
- Ensure nonce is valid

### API Endpoints Not Responding

- Verify REST API is enabled: **Settings > Permalinks** (must not be "Plain")
- Check rate limit: **Tools > BeyondSEO Settings > API**
- Verify authentication token

### Cache Issues

Clear plugin cache:
```php
delete_option('beyond_seo_cache_*');
do_action('beyond_seo_cache_clear');
```

Or via admin: **Tools > BeyondSEO Settings > General > Enable Caching** (toggle off/on)

## Support & Contributing

- **Documentation:** See [SECURITY_CONFIG.md](SECURITY_CONFIG.md), [SECURITY_AUDIT_20260208.md](SECURITY_AUDIT_20260208.md)
- **Issues:** Report through WordPress forums or GitHub
- **Security Issues:** Email security@rankingcoach.com with details

## License

GNU General Public License v2.0 or later

See [LICENSE](LICENSE) or visit https://www.gnu.org/licenses/gpl-2.0.html

## Changelog

### Version 1.1.6 (2026-02-08)

- ✨ Added comprehensive REST API for settings management
- 🛡️ Implemented log redaction middleware for security
- 🧪 Added PHPUnit test suite
- ⚙️ New WordPress admin settings page with tab interface
- 📚 Complete documentation and security configuration
- 🔍 Automated static analysis in CI/CD pipeline
- ✅ Security audit passed - no active issues

## Support

For support, documentation, and updates:

- 🌐 Website: https://www.rankingcoach.com
- 📧 Email: support@rankingcoach.com
- 🐛 Report Issues: https://github.com/rankingcoach/beyond-seo/issues
- 📖 Documentation: See docs/ folder
