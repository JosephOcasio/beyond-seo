<?php /** @noinspection RegExpRedundantEscape */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\SchemaMarkup;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;

/**
 * Class SchemaTypeIdentificationOperation
 *
 * This operation identifies and validates schema markup on a page.
 * It suggests appropriate schema types based on page content and checks
 * if the existing schema is properly implemented.
 */
#[SeoMeta(
    name: 'Schema Type Identification',
    weight: WeightConfiguration::WEIGHT_SCHEMA_TYPE_IDENTIFICATION_OPERATION,
    description: 'Analyzes page content to detect existing schema types and recommends appropriate markup when missing. Validates current structured data implementation to ensure compatibility with search engine standards.',
)]
class SchemaTypeIdentificationOperation extends Operation implements OperationInterface
{
    /**
     * Identifies and validates schema markup for the given post-ID.
     * This method analyzes the page content, extracts existing schema data,
     * and suggests appropriate schema types based on content analysis.
     *
     * @return array|null The analysis results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;

        // Get page details
        $postType = $this->contentProvider->getPostType($postId);

        // Get full HTML content
        $htmlContent = $this->contentProvider->getContent($postId);

        if (empty($htmlContent)) {
            return [
                'success' => false,
                'message' => __('Failed to retrieve content', 'beyond-seo'),
            ];
        }

        // Extract existing schema data
        $existingSchemas = $this->contentProvider->extractSchemaData($htmlContent);

        // Determine if the page content is relevant for local business
        $isRelevantForLocal = $this->contentProvider->isRelevantPageType($postId, $postType);

        // Get local keywords to help with schema type detection
        $localKeywords = $this->contentProvider->getLocalKeywords($postId);

        // Analyze content for local business signals
        $localSignals = $this->contentProvider->analyzeLocalBusinessSignals($htmlContent, $localKeywords);

        // Find LocalBusiness schema if it exists
        $localBusinessSchema = $this->contentProvider->findLocalBusinessSchema($existingSchemas);

        // Suggest the most appropriate schema type
        $suggestedSchemaType = $this->contentProvider->suggestSchemaType(
            $isRelevantForLocal,
            $localSignals,
            $localKeywords
        );

        // Validate the existing schema if found
        $validation = null;
        if ($localBusinessSchema) {
            $validation = $this->contentProvider->validateLocalBusinessSchema($localBusinessSchema);
        }

        // Determine if schema is missing
        $hasSchema = !empty($existingSchemas);
        $hasAppropriateSchema = $hasSchema && $this->hasAppropriateSchema($existingSchemas, $suggestedSchemaType);

        // Analyze the content type to determine other appropriate schema types
        $contentTypeSchemas = $this->contentProvider->identifyContentSpecificSchemas($htmlContent, $postType);

        // Combine all suggested schemas
        $allSuggestedSchemas = array_merge([$suggestedSchemaType], $contentTypeSchemas);
        $allSuggestedSchemas = array_unique($allSuggestedSchemas);

        // Format the results
        return [
            'success' => true,
            'message' => __('Schema analysis completed successfully', 'beyond-seo'),
            'has_schema' => $hasSchema,
            'has_appropriate_schema' => $hasAppropriateSchema,
            'existing_schemas' => $this->formatExistingSchemas($existingSchemas),
            'suggested_schema_types' => $allSuggestedSchemas,
            'primary_suggestion' => $suggestedSchemaType,
            'local_business_schema' => (bool)$localBusinessSchema,
            'local_business_validation' => $validation,
            'is_relevant_for_local' => $isRelevantForLocal,
            'local_signals' => $localSignals,
        ];
    }

    /**
     * Format existing schemas for readable output
     *
     * @param array $schemas Array of extracted schema objects
     * @return array Formatted schema information
     */
    private function formatExistingSchemas(array $schemas): array
    {
        $formattedSchemas = [];

        foreach ($schemas as $schema) {
            $type = $schema['@type'] ?? 'Unknown';

            // Handle an array of types
            if (is_array($type)) {
                $type = implode(', ', $type);
            }

            $formattedSchemas[] = [
                'type' => $type,
                'properties' => array_keys($schema),
                'has_required_properties' => $this->contentProvider->hasRequiredSchemaProperties($schema, $type),
            ];
        }

        return $formattedSchemas;
    }

    /**
     * Check if the page has the appropriate schema markup based on the suggested type
     *
     * @param array $existingSchemas Array of existing schema objects
     * @param string $suggestedType Suggested a schema type
     * @return bool Whether the appropriate schema exists
     */
    private function hasAppropriateSchema(array $existingSchemas, string $suggestedType): bool
    {
        foreach ($existingSchemas as $schema) {
            $type = $schema['@type'] ?? '';

            // Handle an array of types
            if (is_array($type)) {
                if (in_array($suggestedType, $type)) {
                    return true;
                }
                continue;
            }

            // Check if the type matches the suggested type or is a subtype
            if ($type === $suggestedType || $this->contentProvider->belongsToSchemaType($type, $suggestedType)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Evaluate the operation value based on schema identification.
     * Scores how well the page is using appropriate schema markup.
     *
     * @return float A score between 0 and 1, where 1 is a perfect schema implementation
     */
    public function calculateScore(): float
    {
        $factorData = $this->value;

        $baseScore = 0;

        // Check if the page has any schema (40% of score)
        $hasSchema = $factorData['has_schema'] ?? false;
        if ($hasSchema) {
            $baseScore += 0.4;
        }

        // Check if the schema is appropriate for the content (40% of score)
        $hasAppropriateSchema = $factorData['has_appropriate_schema'] ?? false;
        if ($hasAppropriateSchema) {
            $baseScore += 0.4;
        }

        // Check LocalBusiness schema validation if applicable (20% of score)
        $localBusinessSchema = $factorData['local_business_schema'] ?? false;
        if ($localBusinessSchema && isset($factorData['local_business_validation'])) {
            $validation = $factorData['local_business_validation'];
            $isValid = $validation['valid'] ?? false;
            $completeness = $validation['completeness'] ?? 0;

            if ($isValid) {
                $baseScore += 0.1;
            }

            // Add up to 10% based on schema completeness
            $baseScore += min(0.1, $completeness / 1000);
        } elseif (!$localBusinessSchema) {
            // If not a LocalBusiness schema, add the remaining 20% if the appropriate schema exists
            if ($hasAppropriateSchema) {
                $baseScore += 0.2;
            }
        }

        return $baseScore;
    }

    /**
     * Get suggestions for improving schema markup.
     * Provides actionable recommendations based on the schema analysis.
     *
     * @return array An array of suggestion types (from Suggestion enum)
     */
    public function suggestions(): array
    {
        $suggestions = [];
        $factorData = $this->value;

        $hasSchema = $factorData['has_schema'] ?? false;
        $hasAppropriateSchema = $factorData['has_appropriate_schema'] ?? false;
        $primarySuggestion = $factorData['primary_suggestion'] ?? '';

        // If no schema at all, suggest adding schema markup
        if (!$hasSchema) {
            $suggestions[] = Suggestion::MISSING_SCHEMA_MARKUP;
            return $suggestions;
        }

        // If a schema exists but is not appropriate, suggest updating it
        if (!$hasAppropriateSchema && !empty($primarySuggestion)) {
            $suggestions[] = Suggestion::IMPROPER_SCHEMA_TYPE_USED;
        }

        // Check LocalBusiness validation if available
        if (isset($factorData['local_business_validation']) && $factorData['local_business_schema']) {
            $validation = $factorData['local_business_validation'];
            $isValid = $validation['valid'] ?? false;

            if (!$isValid && !empty($validation['missing_required'])) {
                $suggestions[] = Suggestion::INVALID_LOCALBUSINESS_SCHEMA;
            }
        }

        return $suggestions;
    }
}
