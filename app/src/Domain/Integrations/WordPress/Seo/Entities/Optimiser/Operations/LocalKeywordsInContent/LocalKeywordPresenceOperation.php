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
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Configs\SeoOptimiserConfig;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use DOMDocument;
use DOMXPath;

/**
 * Class LocalKeywordPresenceOperation
 *
 * This operation checks for local keyword inclusion in key content areas (titles, headings, body)
 * to ensure content is properly optimized for local SEO. It analyzes keyword presence and distribution
 * across different structural elements of the content.
 */
#[SeoMeta(
    name: 'Local Keyword Presence',
    weight: WeightConfiguration::WEIGHT_LOCAL_KEYWORD_PRESENCE_OPERATION,
    description: 'Analyzes titles, headings, and body text for location-specific keywords to evaluate local SEO strength. Measures keyword distribution across content elements and highlights gaps where local terms could be incorporated.',
)]
class LocalKeywordPresenceOperation extends Operation implements OperationInterface
{
    /**
     * Performs local keyword presence analysis for the given post-ID.
     *
     * @return array|null The check results or null if the post-ID is invalid.
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        // Get local keywords (from post-meta, options, or parameters)
        $localKeywords = $this->contentProvider->getLocalKeywords($postId);

        if (empty($localKeywords)) {
            return [
                'success' => false,
                'message' => __('No local keywords found for analysis', 'beyond-seo')
            ];
        }

        // Fetch full HTML content
        $content = $this->contentProvider->getContent($postId);
        if (empty($content)) {
            return [
                'success' => false,
                'message' => __('Failed to fetch content', 'beyond-seo')
            ];
        }

        // Clean content for text-only analysis
        $cleanContent = $this->contentProvider->cleanContent($content);

        // Parse HTML for structural analysis
        $dom = new DOMDocument();

        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors(false);

        $xpath = new DOMXPath($dom);

        // Analyze local keyword presence in different content areas
        $analysisResults = [
            'title' => $this->contentProvider->analyzeTitle($xpath, $localKeywords, $postId),
            'headings' => $this->contentProvider->analyzeHeadings($xpath, $localKeywords),
            'first_paragraph' => $this->contentProvider->analyzeFirstParagraph($xpath, $localKeywords),
            'body' => $this->contentProvider->analyzeBodyContent($cleanContent, $localKeywords),
            'meta_description' => $this->contentProvider->analyzeMetaDescription($xpath, $localKeywords, $postId)
        ];

        // Calculate overall scores and presence metrics
        $overallScore = $this->calculateOverallScore($analysisResults);
        $keywordPresenceCount = $this->countKeywordPresence($analysisResults, $localKeywords);

        // Prepare final results
        return [
            'success' => true,
            'message' => __('Local keyword presence analysis completed', 'beyond-seo'),
            'local_keywords' => $localKeywords,
            'analysis' => $analysisResults,
            'overall_score' => $overallScore,
            'keyword_presence' => $keywordPresenceCount
        ];
    }

    /**
     * Calculate the overall score based on weighted results from content areas
     *
     * @param array $analysisResults Results from all content areas
     * @return float The overall score (0-1)
     */
    private function calculateOverallScore(array $analysisResults): float
    {
        $weightedSum = 0;
        $totalWeight = 0;

        foreach (SeoOptimiserConfig::KEYWORD_CONTENT_AREAS as $area => $config) {
            if (isset($analysisResults[$area]['score'])) {
                $weightedSum += $analysisResults[$area]['score'] * $config['weight'];
                $totalWeight += $config['weight'];
            }
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0;
    }

    /**
     * Count how many times each local keyword appears in the content
     *
     * @param array $analysisResults Results from all content areas
     * @param array $localKeywords List of local keywords
     * @return array Counts of each keyword's presence
     */
    private function countKeywordPresence(array $analysisResults, array $localKeywords): array
    {
        $keywordCounts = array_fill_keys($localKeywords, 0);
        $keywordAreas = array_fill_keys($localKeywords, []);

        // Count occurrences in the title
        if (isset($analysisResults['title']['keywords_found'])) {
            foreach ($analysisResults['title']['keywords_found'] as $keyword) {
                $keywordCounts[$keyword]++;
                $keywordAreas[$keyword][] = 'title';
            }
        }

        // Count occurrences in headings
        if (isset($analysisResults['headings']['heading_breakdown'])) {
            foreach ($analysisResults['headings']['heading_breakdown'] as $level => $headings) {
                foreach ($headings as $heading) {
                    foreach ($heading['keywords_found'] as $keyword) {
                        $keywordCounts[$keyword]++;
                        $keywordAreas[$keyword][] = $level;
                    }
                }
            }
        }

        // Count occurrences in the first paragraph
        if (isset($analysisResults['first_paragraph']['keywords_found'])) {
            foreach ($analysisResults['first_paragraph']['keywords_found'] as $keyword) {
                $keywordCounts[$keyword]++;
                $keywordAreas[$keyword][] = 'first_paragraph';
            }
        }

        // Count occurrences in meta description
        if (isset($analysisResults['meta_description']['keywords_found'])) {
            foreach ($analysisResults['meta_description']['keywords_found'] as $keyword) {
                $keywordCounts[$keyword]++;
                $keywordAreas[$keyword][] = 'meta_description';
            }
        }

        // Add body content counts
        if (isset($analysisResults['body']['keyword_counts'])) {
            foreach ($analysisResults['body']['keyword_counts'] as $keyword => $count) {
                $keywordCounts[$keyword] += $count;
                $keywordAreas[$keyword][] = 'body';
            }
        }

        return [
            'counts' => $keywordCounts,
            'areas' => $keywordAreas
        ];
    }

    /**
     * Calculate the score based on the performed analysis
     *
     * @return float A score based on local keyword presence in key content areas
     */
    public function calculateScore(): float
    {
        // Use the pre-calculated overall score if available
        if (isset($this->value['overall_score'])) {
            return $this->value['overall_score'];
        }

        // Otherwise, calculate from the area scores
        $analysisResults = $this->value['analysis'] ?? [];
        if (empty($analysisResults)) {
            return 0;
        }

        return $this->calculateOverallScore($analysisResults);
    }

    /**
     * Generate suggestions based on local keyword presence analysis
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];

        // Get factor data for this operation
        $factorData = $this->value;

        if (empty($factorData['analysis'])) {
            return [];
        }

        $analysis = $factorData['analysis'];

        // Check for missing keywords in the title
        if (!$analysis['title']['has_keyword']) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_META_TITLE;
        }

        // Check for missing keywords in headings
        if ($analysis['headings']['headings_with_keywords'] === 0) {
            $activeSuggestions[] = Suggestion::KEYWORDS_MISSING_IN_HEADINGS;
        }

        // Check for missing keywords in the first paragraph
        if (!$analysis['first_paragraph']['has_keyword']) {
            $activeSuggestions[] = Suggestion::KEYWORD_MISSING_IN_FIRST_PARAGRAPH;
        }

        // Check for local keyword distribution issues
        if (isset($analysis['body']['has_keyword']) && $analysis['body']['has_keyword']) {
            if ($analysis['body']['avg_distribution'] < 0.5) {
                $activeSuggestions[] = Suggestion::POOR_KEYWORD_DISTRIBUTION;
            }
        }

        // Check for missing local keywords in meta-description
        if (!$analysis['meta_description']['has_keyword']) {
            $activeSuggestions[] = Suggestion::MISSING_RELATED_KEYWORDS;
        }

        // Check for unbalanced keyword usage across different keywords
        if (isset($factorData['keyword_presence'])) {
            $keywordPresence = $factorData['keyword_presence']['counts'];
            $values = array_values($keywordPresence);

            if (count($values) >= 2) {
                sort($values);
                $min = $values[0];
                $max = end($values);

                // If there's a big difference between the most and least used keywords
                if ($min < 1 && $max > 3 && $max >= $min * 5) {
                    $activeSuggestions[] = Suggestion::UNBALANCED_KEYWORD_DISTRIBUTION;
                }
            }
        }

        return $activeSuggestions;
    }
}
