<?php
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\LocalKeywordsInContent;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;

/**
 * Class LocalSchemaValidationOperation
 *
 * This class validates local business schema markup on web pages.
 * It checks for the presence and correctness of LocalBusiness schema types
 * and required properties to ensure proper local SEO optimization.
 */
#[SeoMeta(
    name: 'Local Schema Validation',
    weight: WeightConfiguration::WEIGHT_LOCAL_SCHEMA_VALIDATION_OPERATION,
    description: 'Validates LocalBusiness schema markup for completeness and accuracy, ensuring required properties exist. Reviews structured data from page content, reporting any missing fields or incorrect types that could hinder local search visibility.',
)]
class LocalSchemaValidationOperation extends Operation implements OperationInterface
{
    /**
     * Performs local schema validation for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get post URL and page content
        $pageUrl = $this->contentProvider->getPostUrl($postId);
        $content = $this->contentProvider->getContent($postId);
        $postType = $this->contentProvider->getPostType($postId);

        // Check if this page type is appropriate for local business schema
        $isRelevantPageType = $this->contentProvider->isRelevantPageType($postId, $postType);

        // Extract all schema.org data from different formats (JSON-LD, Microdata, RDFa)
        $schemas = $this->contentProvider->extractSchemaData($content);

        // Find LocalBusiness schema if any
        $localBusinessSchema = $this->contentProvider->findLocalBusinessSchema($schemas);

        // Validate the schema if it exists
        $validationResults = [];
        $hasValidSchema = false;

        if ($localBusinessSchema) {
            $validationResults = $this->contentProvider->validateLocalBusinessSchema($localBusinessSchema);
            $hasValidSchema = $validationResults['valid'];
        }

        // Prepare result data
        return [
            'success' => true,
            'message' => __('Local schema validation completed', 'beyond-seo'),
            'page_url' => $pageUrl,
            'post_type' => $postType,
            'is_relevant_page_type' => $isRelevantPageType,
            'has_schema_markup' => !empty($schemas),
            'has_local_business_schema' => !empty($localBusinessSchema),
            'schema_data' => $localBusinessSchema,
            'validation_results' => $validationResults,
            'has_valid_schema' => $hasValidSchema
        ];
    }

    /**
     * Calculate the operation score based on the performed analysis
     *
     * @return float Score from 0 to 1
     */
    public function calculateScore(): float
    {
        // If a page type is not relevant for local business schema, return a perfect score
        if (!$this->value['is_relevant_page_type']) {
            return 1.0;
        }

        // If it has valid local business schema, return perfect score
        if ($this->value['has_valid_schema']) {
            return 1.0;
        }

        // If it has a local business schema, but it's not valid, score based on completeness
        if ($this->value['has_local_business_schema']) {
            $completeness = $this->value['validation_results']['completeness'] ?? 0;
            return max(0.1, $completeness / 100); // Minimum score of 0.1 if at least has schema
        }

        // If it has some schema markup but no local business schema
        if ($this->value['has_schema_markup']) {
            return 0.2; // Some schema is better than none
        }

        // No schema markup at all
        return 0;
    }

    /**
     * Generate suggestions based on local schema validation results
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        // Get factor data for this operation
        $factorData = $this->value;

        // If a page type is not relevant for local business schema, no suggestions needed
        if (!$factorData['is_relevant_page_type']) {
            return $activeSuggestions;
        }

        // If no schema markup at all or missing local business schema
        if (!$factorData['has_schema_markup'] || !$factorData['has_local_business_schema']) {
            $activeSuggestions[] = Suggestion::IMPORTANT_RELATED_TERMS_MISSING;
            $activeSuggestions[] = Suggestion::INSUFFICIENT_SEMANTIC_CONTEXT;
            return $activeSuggestions;
        }

        // If a local business schema exists but is invalid
        if (!$factorData['has_valid_schema']) {
            $validationResults = $factorData['validation_results'] ?? [];

            if (!empty($validationResults['missing_required'])) {
                $activeSuggestions[] = Suggestion::IMPORTANT_RELATED_TERMS_MISSING;
            }

            if (!empty($validationResults['incomplete_properties'])) {
                $activeSuggestions[] = Suggestion::INSUFFICIENT_SEMANTIC_CONTEXT;
            }

            if (!empty($validationResults['missing_recommended'])) {
                $activeSuggestions[] = Suggestion::MISSING_RELATED_KEYWORDS;
            }
        }

        return $activeSuggestions;
    }
}
