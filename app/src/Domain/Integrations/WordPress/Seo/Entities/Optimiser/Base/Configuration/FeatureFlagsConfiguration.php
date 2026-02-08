<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration;

/**
 * Class FeatureFlagsConfiguration
 *
 * This class defines the feature flags configuration for the SEO optimiser operations.
 * It allows enabling or disabling specific features globally, per context, per factor, or per operation.
 */
class FeatureFlagsConfiguration
{
    /**
     * Get the feature flags configuration.
     *
     * @return array The feature flags configuration.
     */
    public function get(): array
    {
        return [
            /**
             * Global feature flags that apply to all operations
             */
            'global' => [
                // Enable/disable external API calls for all operations
                'external_api_call' => false,
                // Controls whether the operation uses caching for its results
                'enable_caching' => false,
                // Controls whether the operation generates suggestions based on the analysis
                'enable_suggestions' => true,
                // Controls whether the operation is available for use
                'available' => true,
            ],

            /**
             * Context-specific feature flags
             * These override the global settings for specific contexts
             */
            'contexts' => [

            ],

            /**
             * Factor-specific feature flags
             * These override the context and global settings for specific factors
             */
            'factors' => [

            ],

            /**
             * Operation-specific feature flags
             * These override the factor, context and global settings for specific operations
             */
            'operations' => [
                'local_keyword_meta_tag_optimization_operation' => [
                    'available' => false,
                ],
                'local_keyword_presence_operation' => [
                    'available' => false,
                ],
                'local_schema_markup_suggestion_operation' => [
                    'available' => false
                ],
                'local_schema_validation_operation' => [
                    'available' => false,
                ],
                'alt_text_presence_check_operation' => [],
                'descriptive_alt_text_operation' => [],
                'primary_keyword_in_alt_text_operation' => [],
                'referring_domains_analysis_operation' => [
                    'available' => false,
                    'external_api_call' => false,
                ],
                'referring_links_quality_assessment_operation' => [
                    'available' => false,
                    'external_api_call' => false,
                ],
                'keyword_competition_volume_check_operation' => [
                    'available' => false,
                    'external_api_call' => false,
                ],
                'keyword_mapping_content_operation' => [],
                'primary_secondary_keywords_validation_operation' => [],
                'meta_description_cta_validation_operation' => [],
                'meta_description_length_check_operation' => [],
                'meta_title_length_check_operation' => [],
                'meta_title_quality_analyzer_operation' => [],
                'first_paragraph_keyword_check_operation' => [],
                'first_paragraph_keyword_stuffing_operation' => [],
                'opening_paragraph_engagement_analysis_operation' => [
                    // This operation if does not have external API calls, will try to use the local logic
                    'external_api_call' => false,
                    'available' => false,
                ],
                'broken_links_identification_operation' => [],
                'fixing_header_consistency_operation' => [],
                'header_hierarchy_check_operation' => [],
                'keywords_in_header_check_operation' => [],
                'content_depth_validation_operation' => [
                    'external_api_call::userIntent' => false,
                    'external_api_call::semanticDepth' => false,
                ],
                'content_length_validation_operation' => [],
                'multimedia_inclusion_check_operation' => [],
                'readability_validation_operation' => [],
                'description_keyword_overuse_operation' => [],
                'primary_secondary_keyword_check_operation' => [],
                'primary_keyword_check_operation' => [],
                'secondary_keywords_check_operation' => [],
                'image_compression_validation_operation' => [],
                'next_gen_image_format_validation_operation' => [],
                'responsive_image_sizing_operation' => [],
                'content_update_suggestions_operation' => [
                    'available' => false,
                    'external_api_call' => false,
                ],
                'keyword_density_validation_operation' => [],
                'keyword_distribution_operation' => [],
                'related_keyword_inclusion_operation' => [],
                'optimize_page_speed_operation' => [
                    'available' => false,
                    'external_api_call' => false,
                ],
                'audience_targeted_adjustments_operation' => [],
                'content_formatting_validation_operation' => [],
                'readability_score_validation_operation' => [],
                'hyphens_instead_of_underscores_operation' => [],
                'primary_keyword_in_url_operation' => [],
                'url_length_check_operation' => [],
                'url_readability_operation' => [],
                'schema_markup_validation_operation' => [],
                'schema_type_identification_operation' => [
                    'available' => false,
                ],
                'google_and_bing_indexation_check_operation' => [
                    'available' => false,
                    'external_api_call' => false
                ],
                'robots_meta_tag_validation_operation' => [
                    'meta_robots_issues' => false,
                    'x_robots_tag_issues' => false,
                ],
                'robots_txt_validation_operation' => [],
                'safe_browsing_check_operation' => [
                    'available' => false,
                    'external_api_call' => false,
                ],
                'canonical_tag_validation_operation' => [],
                'cross_domain_canonical_check_operation' => [],
                'duplicate_content_detection_operation' => [],
            ],
        ];
    }
}
