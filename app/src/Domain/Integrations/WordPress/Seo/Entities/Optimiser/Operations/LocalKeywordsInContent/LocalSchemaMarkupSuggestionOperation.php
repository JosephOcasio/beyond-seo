<?php /** @noinspection PhpTooManyParametersInspection */
/** @noinspection RegExpUnnecessaryNonCapturingGroup */
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
 * Class LocalSchemaMarkupSuggestionOperation
 *
 * This class analyzes content to provide schema markup recommendations
 * focused on local business implementations. It checks for the presence of
 * location signals, evaluates existing schema, and suggests appropriate
 * local business structured data implementation.
 */
#[SeoMeta(
    name: 'Local Schema Markup Suggestion',
    weight: WeightConfiguration::WEIGHT_LOCAL_SCHEMA_MARKUP_SUGGESTION_OPERATION,
    description: 'Examines content for local business indicators and existing structured data. Recommends appropriate schema markup when location signals are missing, helping search engines understand and display local information more effectively.',
)]
class LocalSchemaMarkupSuggestionOperation extends Operation implements OperationInterface
{
    /**
     * Performs local schema markup analysis for the given post-ID.
     *
     * @return array|null The analysis results or null if the post-ID is invalid
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get post-content and metadata
        $content = $this->contentProvider->getContent($postId);
        $postType = $this->contentProvider->getPostType($postId);

        // Check if the page is relevant for local business schema
        $isRelevantPage = $this->contentProvider->isRelevantPageType($postId, $postType);

        // Extract any existing schema data from the content
        $existingSchemas = $this->contentProvider->extractSchemaData($content);
        $localBusinessSchema = $this->contentProvider->findLocalBusinessSchema($existingSchemas);

        // Get local keywords and location data
        $localKeywords = $this->contentProvider->getLocalKeywords($postId);

        // Analyze the content for local business signals
        $localSignals = $this->contentProvider->analyzeLocalBusinessSignals($content, $localKeywords);

        // Validate existing local business schema if available
        $schemaValidation = null;
        if ($localBusinessSchema) {
            $schemaValidation = $this->contentProvider->validateLocalBusinessSchema($localBusinessSchema);
        }

        // Determine if schema implementation is recommended
        $needsSchema = $this->determineSchemaRequirement(
            $isRelevantPage,
            $localSignals,
            $localBusinessSchema,
            $schemaValidation
        );

        // Generate schema recommendation
        $schemaRecommendation = $this->generateSchemaRecommendation(
            $needsSchema,
            $isRelevantPage,
            $localBusinessSchema,
            $schemaValidation,
            $localSignals,
            $localKeywords
        );

        // Prepare results
        return [
            'success' => true,
            'message' => __('Local schema markup analysis completed successfully', 'beyond-seo'),
            'post_id' => $postId,
            'post_type' => $postType,
            'is_relevant_page' => $isRelevantPage,
            'local_keywords' => $localKeywords,
            'local_signals' => $localSignals,
            'existing_schema' => (bool)$localBusinessSchema,
            'schema_validation' => $schemaValidation,
            'needs_schema' => $needsSchema,
            'recommendation' => $schemaRecommendation
        ];
    }

    /**
     * Determine if schema implementation is required based on analysis
     *
     * @param bool $isRelevantPage Whether the page type is relevant for local schema
     * @param array $localSignals Local business signals detected in the content
     * @param array|null $existingSchema Existing schema if available
     * @param array|null $schemaValidation Validation results for existing schema
     * @return bool Whether schema implementation is recommended
     */
    private function determineSchemaRequirement(
        bool $isRelevantPage,
        array $localSignals,
        ?array $existingSchema,
        ?array $schemaValidation
    ): bool
    {
        // If there's an existing valid schema, we don't need a new implementation
        if ($existingSchema && $schemaValidation && $schemaValidation['valid']) {
            return false;
        }

        // If it's a relevant page type, schema is recommended
        if ($isRelevantPage) {
            return true;
        }

        // If there are strong local signals but no schema, it's recommended
        if ($localSignals['signal_strength'] >= 0.6 && !$existingSchema) {
            return true;
        }

        // If there's an existing schema, but it's invalid or incomplete (below 70% complete), recommend improvements
        if ($existingSchema && $schemaValidation && (!$schemaValidation['valid'] || $schemaValidation['completeness'] < 70)) {
            return true;
        }

        return false;
    }

    /**
     * Generate schema recommendation based on analysis
     *
     * @param bool $needsSchema Whether schema implementation is needed
     * @param bool $isRelevantPage Whether the page is relevant for local schema
     * @param array|null $existingSchema Existing schema if available
     * @param array|null $schemaValidation Validation results for existing schema
     * @param array $localSignals Local business signals detected in the content
     * @param array $localKeywords Local keywords for the page
     * @return array Schema recommendation details
     */
    private function generateSchemaRecommendation(
        bool $needsSchema,
        bool $isRelevantPage,
        ?array $existingSchema,
        ?array $schemaValidation,
        array $localSignals,
        array $localKeywords
    ): array
    {
        if (!$needsSchema) {
            if ($existingSchema && $schemaValidation && $schemaValidation['valid']) {
                return [
                    'type' => 'no_action',
                    'message' => __('Your page already has valid LocalBusiness schema markup. No action required.', 'beyond-seo'),
                    'schema_type' => $schemaValidation['schema_type'],
                    'details' => __('The existing schema is valid and complete.', 'beyond-seo')
                ];
            }

            return [
                'type' => 'no_action',
                'message' => __('Local business schema is not necessary for this page.', 'beyond-seo'),
                'details' => __('This page does not contain sufficient local business information to warrant schema markup.', 'beyond-seo')
            ];
        }

        // If there's an existing schema that needs improvement
        if ($existingSchema && $schemaValidation) {
            $recommendationType = $schemaValidation['valid'] ? 'improve' : 'fix';
            $details = [];

            if (!empty($schemaValidation['missing_required'])) {
                /* translators: %s is a list of missing required properties */
                $details[] = sprintf(__('Required properties missing: %s', 'beyond-seo'), implode(', ', $schemaValidation['missing_required']));
            }

            if (!empty($schemaValidation['missing_recommended'])) {
                /* translators: %s is a list of missing recommended properties */
                $details[] = sprintf(__('Recommended properties missing: %s', 'beyond-seo'), implode(', ', $schemaValidation['missing_recommended']));
            }

            if (!empty($schemaValidation['incomplete_properties'])) {
                /* translators: %s is a list of incomplete properties */
                $details[] = sprintf(__('Incomplete properties: %s', 'beyond-seo'), implode(', ', $schemaValidation['incomplete_properties']));
            }

            return [
                'type' => $recommendationType,
                /* translators: %s is either "fixing" or "improvement" */
                'message' => sprintf(__('Your existing LocalBusiness schema needs %s.', 'beyond-seo'), ($recommendationType === 'fix' ? __('fixing', 'beyond-seo') : __('improvement', 'beyond-seo'))),
                'schema_type' => $schemaValidation['schema_type'],
                'completeness' => $schemaValidation['completeness'] . '%',
                'details' => $details
            ];
        }

        // For pages that need new schema implementation
        $suggestedSchemaType = $this->contentProvider->suggestSchemaType($isRelevantPage, $localSignals, $localKeywords);

        return [
            'type' => 'implement',
            'message' => __('This page would benefit from LocalBusiness schema markup.', 'beyond-seo'),
            'schema_type' => $suggestedSchemaType,
            'details' => __('Adding structured data will help search engines better understand your business information.', 'beyond-seo'),
            'implementation_priority' => $this->calculateImplementationPriority($isRelevantPage, $localSignals)
        ];
    }

    /**
     * Calculate implementation priority based on page relevance and local signals
     *
     * @param bool $isRelevantPage Whether the page is relevant for local schema
     * @param array $localSignals Local business signals detected in the content
     * @return string Priority level (high, medium, low)
     */
    private function calculateImplementationPriority(bool $isRelevantPage, array $localSignals): string
    {
        if ($isRelevantPage && $localSignals['signal_strength'] >= 0.7) {
            return 'high';
        } elseif ($isRelevantPage || $localSignals['signal_strength'] >= 0.5) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate a score based on the performed analysis
     *
     * @return float A score between 0 and 1 representing schema implementation quality
     */
    public function calculateScore(): float
    {
        // If the page doesn't need schema, and there's no recommendation to add it, the score is perfect
        if (!$this->value['needs_schema'] && $this->value['recommendation']['type'] === 'no_action') {
            return 1.0;
        }

        // If the existing schema is valid, the score is perfect
        if (!$this->value['needs_schema'] &&
            isset($this->value['schema_validation']) &&
            $this->value['schema_validation']['valid']) {
            return 1.0;
        }

        // If a schema is needed but not implemented, the score is 0
        if ($this->value['needs_schema'] && !$this->value['existing_schema']) {
            return 0;
        }

        // If a schema exists but needs improvement, score based on completeness
        if ($this->value['needs_schema'] &&
            $this->value['existing_schema'] &&
            isset($this->value['schema_validation'])) {
            return $this->value['schema_validation']['completeness'] / 100;
        }

        // If a page doesn't show strong local signals and doesn't have schema, score based on signal strength
        if (!$this->value['is_relevant_page'] && !$this->value['existing_schema']) {
            // Low signals mean schema isn't as important, so the score is moderate
            return 0.5 + (0.5 * (1 - $this->value['local_signals']['signal_strength']));
        }

        // Default fallback score
        return 0.3;
    }

    /**
     * Generate suggestions based on schema markup analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $factorData = $this->value;
        $activeSuggestions = [];

        // If schema is needed but not implemented
        if ($factorData['needs_schema'] && !$factorData['existing_schema']) {
            // This indicates missing content depth specifically for local business information
            $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;

            // Suggest expanding content to include structured data elements
            $activeSuggestions[] = Suggestion::CONTENT_EXPANSION;

            // User intent for local searches is not being satisfied
            if ($factorData['is_relevant_page']) {
                $activeSuggestions[] = Suggestion::INTENT_NOT_SATISFIED;
            }
        }

        // If the schema exists but needs improvement
        if ($factorData['needs_schema'] &&
            $factorData['existing_schema'] &&
            isset($factorData['schema_validation']) &&
            !$factorData['schema_validation']['valid']) {

            // If important properties are missing, content lacks depth
            if (!empty($factorData['schema_validation']['missing_required'])) {
                $activeSuggestions[] = Suggestion::LACKS_CONTENT_DEPTH;
            }

            // If recommended properties are missing, suggest content expansion
            if (!empty($factorData['schema_validation']['missing_recommended'])) {
                $activeSuggestions[] = Suggestion::CONTENT_EXPANSION;
            }
        }

        // If relevant page but signals are weak, suggest content improvements
        if ($factorData['is_relevant_page'] && $factorData['local_signals']['signal_strength'] < 0.5) {
            // Page has a purpose but doesn't satisfy user intent well
            $activeSuggestions[] = Suggestion::INTENT_NOT_SATISFIED;

            // Suggest adding more detailed local content
            if ($factorData['local_signals']['signal_strength'] < 0.3) {
                $activeSuggestions[] = Suggestion::CONTENT_TOO_SHORT;
            }
        }

        // Use a custom approach to get unique enum values
        return $this->getUniqueEnumValues($activeSuggestions);
    }
}
