# REST API Documentation

Complete documentation for BeyondSEO REST API endpoints.

## Overview

The BeyondSEO REST API provides programmatic access to plugin settings and configuration.

**Base URL:** `https://example.com/api/settings`

**Authentication:** WordPress REST API authentication (cookie, JWT token, or application password)

**Response Format:** JSON

**API Version:** v1

## Authentication

### Method 1: WordPress Cookie

For browser-based requests:

```bash
curl -b "wordpress_logged_in=..." https://example.com/api/settings
```

### Method 2: Application Password

Generate in **Users > Your Profile > Application Passwords**:

```bash
curl -u "username:app_password" https://example.com/api/settings
```

### Method 3: JWT Token

```bash
# Get token (if JWT plugin installed)
curl -X POST https://example.com/wp-json/jwt-auth/v1/token \
  -d "username=admin&password=password"

# Use token
curl -H "Authorization: Bearer JWT_TOKEN" https://example.com/api/settings
```

## Endpoints

### 1. Get All Settings

Returns all plugin settings grouped by category.

```http
GET /api/settings
```

**Response:** 200 OK

```json
{
  "general": {
    "enabled": true,
    "debug_mode": false,
    "cache_enabled": true,
    "cache_ttl": 3600
  },
  "seo": {
    "enable_xml_sitemap": true,
    "enable_breadcrumbs": true,
    "enable_schema_markup": true,
    "default_separator": "-"
  },
  "api": {
    "api_enabled": false,
    "api_rate_limit": 1000
  }
}
```

**cURL Example:**
```bash
curl -X GET https://example.com/api/settings \
  -u "admin:password"
```

**JavaScript Example:**
```javascript
fetch('https://example.com/api/settings', {
  credentials: 'include',
  headers: {
    'Authorization': 'Basic ' + btoa('admin:password')
  }
})
.then(r => r.json())
.then(data => console.log(data));
```

### 2. Get Specific Setting

Retrieve a single setting by key.

```http
GET /api/settings/{key}
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `key` | string | Setting key (e.g., `cache_ttl`, `enable_xml_sitemap`) |

**Response:** 200 OK

```json
{
  "key": "cache_ttl",
  "value": 3600
}
```

**Not Found:** 404

```json
{
  "error": "Setting not found",
  "key": "invalid_key"
}
```

**cURL Example:**
```bash
curl -X GET https://example.com/api/settings/cache_ttl \
  -u "admin:password"
```

### 3. Create/Update Setting

Create a new setting or update an existing one.

```http
POST /api/settings/{key}
PUT /api/settings/{key}
```

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | string | Yes | Setting key |
| `value` | any | Yes | Setting value (auto-sanitized) |

**Request Body:**

```json
{
  "value": 7200
}
```

**Response:** 200 OK

```json
{
  "success": true,
  "key": "cache_ttl",
  "value": 7200,
  "message": "Setting updated successfully"
}
```

**Request Validation Error:** 400 Bad Request

```json
{
  "error": "Missing required field",
  "field": "value"
}
```

**cURL Example:**
```bash
curl -X POST https://example.com/api/settings/cache_ttl \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{"value": 7200}'
```

### 4. Batch Update Settings

Update multiple settings in one request.

```http
POST /api/settings/batch
```

**Request Body:**

```json
{
  "settings": {
    "cache_enabled": true,
    "cache_ttl": 7200,
    "enable_xml_sitemap": true,
    "default_separator": "|"
  }
}
```

**Response:** 200 OK

```json
{
  "success": true,
  "updated": {
    "cache_enabled": true,
    "cache_ttl": 7200,
    "enable_xml_sitemap": true,
    "default_separator": "|"
  },
  "message": "4 settings updated"
}
```

**Request Validation Error:** 400 Bad Request

```json
{
  "error": "Invalid request format",
  "expected": "{\"settings\": {\"key\": \"value\", ...}}"
}
```

**cURL Example:**
```bash
curl -X POST https://example.com/api/settings/batch \
  -u "admin:password" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": {
      "cache_ttl": 7200,
      "enable_breadcrumbs": false
    }
  }'
```

### 5. Delete Setting

Remove a setting.

```http
DELETE /api/settings/{key}
```

**Parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `key` | string | Setting key to delete |

**Response:** 200 OK

```json
{
  "success": true,
  "key": "cache_ttl",
  "message": "Setting deleted successfully"
}
```

**cURL Example:**
```bash
curl -X DELETE https://example.com/api/settings/cache_ttl \
  -u "admin:password"
```

## Setting Keys Reference

### General Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | true | Enable/disable plugin |
| `debug_mode` | bool | false | Enable debug logging |
| `cache_enabled` | bool | true | Enable response caching |
| `cache_ttl` | int | 3600 | Cache time-to-live (seconds) |

### SEO Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enable_xml_sitemap` | bool | true | Generate XML sitemaps |
| `enable_breadcrumbs` | bool | true | Show breadcrumb navigation |
| `enable_schema_markup` | bool | true | Add schema.org markup |
| `default_separator` | string | "-" | Title separator character |

### API Settings

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `api_enabled` | bool | false | Enable REST API |
| `api_rate_limit` | int | 1000 | Requests per hour |

## Error Handling

### Common Error Responses

**401 Unauthorized:**
```json
{
  "code": "rest_forbidden",
  "message": "Sorry, you are not allowed to do this.",
  "data": {
    "status": 401
  }
}
```

**403 Forbidden:**
```json
{
  "code": "rest_user_cannot_delete_post",
  "message": "Sorry, you are not allowed to delete this resource.",
  "data": {
    "status": 403
  }
}
```

**500 Internal Server Error:**
```json
{
  "error": "Failed to update setting",
  "message": "Database update failed"
}
```

### Error Codes

| Code | Status | Meaning |
|------|--------|---------|
| `rest_forbidden` | 403 | User lacks required permissions |
| `rest_invalid_param` | 400 | Invalid parameter |
| `invalid_request_format` | 400 | Malformed request body |
| `setting_not_found` | 404 | Setting key doesn't exist |

## Rate Limiting

API requests are rate-limited per WordPress user.

**Limit:** Configurable via admin settings (default: 1000 requests/hour)

**Headers in response:**
```http
X-RateLimit-Limit: 1000
X-RateLimit-Remaining: 999
X-RateLimit-Reset: 1644325200
```

When limit exceeded:

```http
HTTP/1.1 429 Too Many Requests

{
  "error": "Rate limit exceeded",
  "message": "Please wait before making more requests",
  "retry_after": 3600
}
```

## Examples

### Node.js / Fetch

```javascript
const apiUrl = 'https://example.com/api/settings';
const auth = btoa('admin:password');

// Get all settings
fetch(apiUrl, {
  headers: { 'Authorization': `Basic ${auth}` }
})
.then(r => r.json())
.then(data => console.log(data));

// Update setting
fetch(`${apiUrl}/cache_ttl`, {
  method: 'POST',
  headers: {
    'Authorization': `Basic ${auth}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({ value: 7200 })
})
.then(r => r.json())
.then(data => console.log('Updated:', data));
```

### Python / Requests

```python
import requests
from requests.auth import HTTPBasicAuth

url = 'https://example.com/api/settings'
auth = HTTPBasicAuth('admin', 'password')

# Get all settings
response = requests.get(url, auth=auth)
print(response.json())

# Update setting
response = requests.post(
    f'{url}/cache_ttl',
    json={'value': 7200},
    auth=auth
)
print(response.json())

# Batch update
response = requests.post(
    f'{url}/batch',
    json={
        'settings': {
            'cache_ttl': 7200,
            'debug_mode': False
        }
    },
    auth=auth
)
print(response.json())
```

### PHP / WordPress

```php
<?php
$url = 'https://example.com/api/settings';
$user = 'admin';
$password = 'password';

// Get all settings
$response = wp_remote_get($url, [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode("$user:$password")
    ]
]);

$settings = json_decode(wp_remote_retrieve_body($response), true);
print_r($settings);

// Update setting
$response = wp_remote_post("$url/cache_ttl", [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode("$user:$password"),
        'Content-Type' => 'application/json'
    ],
    'body' => json_encode(['value' => 7200])
]);

$result = json_decode(wp_remote_retrieve_body($response), true);
echo "Updated: " . ($result['success'] ? 'Yes' : 'No');
?>
```

### cURL

```bash
#!/bin/bash

API_URL="https://example.com/api/settings"
USER="admin"
PASS="password"
AUTH="$USER:$PASS"

# Get all settings
curl -u "$AUTH" "$API_URL"

# Get specific setting
curl -u "$AUTH" "$API_URL/cache_ttl"

# Create/update setting
curl -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{"value": 7200}' \
  "$API_URL/cache_ttl"

# Batch update
curl -u "$AUTH" \
  -H "Content-Type: application/json" \
  -d '{
    "settings": {
      "cache_ttl": 7200,
      "debug_mode": false
    }
  }' \
  "$API_URL/batch"

# Delete setting
curl -u "$AUTH" -X DELETE "$API_URL/cache_ttl"
```

## Webhooks (Future)

Planned for v1.2:
- POST events to external URLs on setting changes
- Webhook history and logs
- Retry logic for failed deliveries

## Changelog

### v1.0.0 (2026-02-08)

- Initial REST API
- GET all/single settings
- POST create/update settings
- POST batch updates
- DELETE settings
- Rate limiting
- Full documentation

---

For more information, see [README.md](README.md) and [TESTING.md](TESTING.md).
