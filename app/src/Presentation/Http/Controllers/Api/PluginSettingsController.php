<?php

declare(strict_types=1);

namespace App\Presentation\Http\Controllers\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

/**
 * PluginSettingsController
 *
 * REST API endpoints for managing plugin settings.
 * Provides CRUD operations for plugin configuration.
 *
 * @Route("/api/settings", name="api_settings_")
 */
class PluginSettingsController extends AbstractController
{
    /**
     * Get all plugin settings
     *
     * @Route("", name="index", methods={"GET"})
     */
    public function index(): JsonResponse
    {
        try {
            $settings = [
                'general' => $this->getGeneralSettings(),
                'seo' => $this->getSeoSettings(),
                'api' => $this->getApiSettings(),
            ];

            return $this->json($settings, Response::HTTP_OK, [], [
                'groups' => ['settings:read'],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve settings',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get specific setting by key
     *
     * @Route("/{key}", name="show", methods={"GET"})
     */
    public function show(string $key): JsonResponse
    {
        try {
            $value = get_option('beyond_seo_' . sanitize_key($key));

            if ($value === false) {
                return $this->json([
                    'error' => 'Setting not found',
                    'key' => $key,
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json([
                'key' => $key,
                'value' => $value,
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to retrieve setting',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create or update a setting
     *
     * @Route("/{key}", name="store", methods={"POST", "PUT"})
     */
    public function store(Request $request, string $key): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $data = json_decode($request->getContent(), true);

            if (!isset($data['value'])) {
                return $this->json([
                    'error' => 'Missing required field',
                    'field' => 'value',
                ], Response::HTTP_BAD_REQUEST);
            }

            $sanitized_key = sanitize_key($key);
            $sanitized_value = $this->sanitizeSetting($sanitized_key, $data['value']);

            update_option('beyond_seo_' . $sanitized_key, $sanitized_value);

            return $this->json([
                'success' => true,
                'key' => $sanitized_key,
                'value' => $sanitized_value,
                'message' => 'Setting updated successfully',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to update setting',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Batch update settings
     *
     * @Route("/batch", name="batch", methods={"POST"})
     */
    public function batch(Request $request): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $data = json_decode($request->getContent(), true);

            if (!isset($data['settings']) || !is_array($data['settings'])) {
                return $this->json([
                    'error' => 'Invalid request format',
                    'expected' => '{"settings": {"key": "value", ...}}',
                ], Response::HTTP_BAD_REQUEST);
            }

            $updated = [];
            foreach ($data['settings'] as $key => $value) {
                $sanitized_key = sanitize_key($key);
                $sanitized_value = $this->sanitizeSetting($sanitized_key, $value);
                update_option('beyond_seo_' . $sanitized_key, $sanitized_value);
                $updated[$sanitized_key] = $sanitized_value;
            }

            return $this->json([
                'success' => true,
                'updated' => $updated,
                'message' => count($updated) . ' settings updated',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Batch update failed',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a setting
     *
     * @Route("/{key}", name="destroy", methods={"DELETE"})
     */
    public function destroy(string $key): JsonResponse
    {
        try {
            $this->denyAccessUnlessGranted('ROLE_ADMIN');

            $sanitized_key = sanitize_key($key);
            delete_option('beyond_seo_' . $sanitized_key);

            return $this->json([
                'success' => true,
                'key' => $sanitized_key,
                'message' => 'Setting deleted successfully',
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Failed to delete setting',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get general plugin settings
     */
    private function getGeneralSettings(): array
    {
        return [
            'enabled' => get_option('beyond_seo_enabled', true),
            'debug_mode' => get_option('beyond_seo_debug_mode', false),
            'cache_enabled' => get_option('beyond_seo_cache_enabled', true),
            'cache_ttl' => intval(get_option('beyond_seo_cache_ttl', 3600)),
        ];
    }

    /**
     * Get SEO-specific settings
     */
    private function getSeoSettings(): array
    {
        return [
            'enable_xml_sitemap' => get_option('beyond_seo_enable_xml_sitemap', true),
            'enable_breadcrumbs' => get_option('beyond_seo_enable_breadcrumbs', true),
            'enable_schema_markup' => get_option('beyond_seo_enable_schema_markup', true),
            'default_separator' => get_option('beyond_seo_default_separator', '-'),
        ];
    }

    /**
     * Get API-specific settings
     */
    private function getApiSettings(): array
    {
        return [
            'api_enabled' => get_option('beyond_seo_api_enabled', false),
            'api_rate_limit' => intval(get_option('beyond_seo_api_rate_limit', 1000)),
        ];
    }

    /**
     * Sanitize setting value based on key
     */
    private function sanitizeSetting(string $key, mixed $value): mixed
    {
        return match ($key) {
            'cache_ttl', 'api_rate_limit' => intval($value),
            'enabled', 'cache_enabled', 'debug_mode', 'enable_xml_sitemap',
            'enable_breadcrumbs', 'enable_schema_markup', 'api_enabled' => (bool) $value,
            default => sanitize_text_field($value),
        };
    }
}
