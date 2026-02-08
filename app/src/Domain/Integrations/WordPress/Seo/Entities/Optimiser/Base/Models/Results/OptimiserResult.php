<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Models\Results;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Adapters\WordPressProvider;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\CategorizedSuggestions;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Factor;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\FactorSuggestions;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContext;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\OptimiserContexts;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\SeoOptimiser;
use App\Domain\Integrations\WordPress\Seo\Libs\ContentFetcher;
use DDD\Domain\Base\Entities\ValueObject;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use Throwable;

/**
 * Class OptimiserResult
 *
 * This class represents the result of an SEO optimisation analysis.
 *
 */
class OptimiserResult extends ValueObject
{
    /** @var float The overall score of the SEO analysis */
    public float $score;

    /** @var OptimiserContexts Contexts */
    public OptimiserContexts $contexts;

    /** @var FactorSuggestions The results of the operations performed */
    public FactorSuggestions $topSuggestions;

    /** @var CategorizedSuggestions The suggestions categorized by type */
    public CategorizedSuggestions $categorizedSuggestions;

    /** @var int The total number of unique suggestions */
    public int $totalSuggestionsCount = 0;

    /** @var string The date and time when the analysis was performed */
    public string $analyzedAt;

    /** @var array<string> The URLs that were consumed during the analysis */
    public array $urlsConsumed = [];

    /** @var array<string, mixed> The post data */
    public array $post = [];

    /** @var int Get maximum number of suggestions */
    private const TOP_SUGGESTIONS_LIMIT = 100;

    /**
     * Constructor for initializing the object with overall score, operation results, factors, and analysis date.
     *
     * @param int $postId
     * @param float $overallScore The overall score of the SEO analysis.
     * @param OptimiserContexts $contexts The factors evaluated during the analysis.
     * @param FactorSuggestions $topSuggestions
     * @param string $analyzedAt The date and time when the analysis was performed.
     * @param int $totalSuggestionsCount
     * @param array $urlsConsumed The URLs that were consumed during the analysis.
     * @param CategorizedSuggestions|null $categorizedSuggestions The suggestions categorized by type.
     */
    public function __construct(
        int $postId,
        float $overallScore,
        OptimiserContexts $contexts,
        FactorSuggestions $topSuggestions,
        string $analyzedAt,
        int $totalSuggestionsCount = 0,
        array $urlsConsumed = [],
        ?CategorizedSuggestions $categorizedSuggestions = null
    ) {
        parent::__construct();
        $this->score = $overallScore;
        $this->contexts = $contexts;
        $this->topSuggestions = $topSuggestions;
        $this->analyzedAt = $analyzedAt;
        $this->totalSuggestionsCount = $totalSuggestionsCount;
        $this->urlsConsumed = $urlsConsumed;
        $this->categorizedSuggestions = $categorizedSuggestions ?? new CategorizedSuggestions();
        $this->post = WordpressHelpers::retrieve_post($postId, true);
    }

    /**
     * Create an OptimiserResult from a SeoOptimiser domain object
     *
     * @param SeoOptimiser $seoOptimiser
     * @return self
     * @throws Throwable
     */
    public static function fromOptimiser(SeoOptimiser $seoOptimiser): self
    {
        $allSuggestions = new FactorSuggestions();
        /** @var OptimiserContext $context */
        foreach ($seoOptimiser->contexts as $context) {
            /** @var Factor $factor */
            $contextSuggestions = $context->getContextSuggestions();
            foreach ($contextSuggestions as $suggestion) {
                if ($allSuggestions->getByUniqueKey($suggestion->uniqueKey()) === $suggestion) {
                    continue;
                }
                $allSuggestions->add($suggestion);
            }
        }

        $limitedTopSuggestions = self::getTopSuggestions($allSuggestions->orderBy('priority'));
        $score = round($seoOptimiser->score, 2);

        // Calculate and set the total suggestions count
        $totalSuggestionsCount = self::getTotalSuggestionsCount($seoOptimiser);

        // Generate categorized suggestions
        $categorizedSuggestions = self::categorizeSuggestions($allSuggestions);

        return new self(
            $seoOptimiser->postId,
            $score,
            $seoOptimiser->contexts,
            $limitedTopSuggestions,
            gmdate('Y-m-d H:i:s', $seoOptimiser->analysisDate->getTimestamp()),
            $totalSuggestionsCount,
            array_merge(ContentFetcher::getUrlsFromCache(), WordPressProvider::getUrlsFromCache()),
            $categorizedSuggestions
        );
    }

    /**
     * Get the top suggestions from the analysis suggestions
     * @param FactorSuggestions $analyzeSuggestions
     * @return FactorSuggestions
     */
    public static function getTopSuggestions(FactorSuggestions $analyzeSuggestions): FactorSuggestions
    {
        $topSuggestions = new FactorSuggestions();
        foreach ($analyzeSuggestions->getElements() as $suggestion) {
            if ($topSuggestions->count() >= self::TOP_SUGGESTIONS_LIMIT) {
                break;
            }
            $topSuggestions->add($suggestion);
        }
        return $topSuggestions;
    }

    /**
     * Categorize suggestions based on their issue type
     *
     * @param FactorSuggestions $suggestions The suggestions to categorize
     * @return CategorizedSuggestions Categorized suggestions
     */
    public static function categorizeSuggestions(FactorSuggestions $suggestions): CategorizedSuggestions
    {
        $categorizedSuggestions = new CategorizedSuggestions();

        foreach ($suggestions->getElements() as $suggestion) {
            $categorizedSuggestions->addSuggestion($suggestion);
        }

        // Sort all categories by priority
        $categorizedSuggestions->sortByPriority();

        return $categorizedSuggestions;
    }


    /**
     * Calculate the total number of suggestions from a page analysis
     *
     * This method counts all unique suggestions across all factors in all contexts
     *
     * @param SeoOptimiser $result The optimiser result to analyze
     * @return int The total number of unique suggestions
     * @throws Throwable
     */
    public static function getTotalSuggestionsCount(SeoOptimiser $result): int
    {
        $uniqueSuggestions = [];

        // Iterate through all contexts
        foreach ($result->contexts->getElements() as $context) {
            /** @var OptimiserContext $context */
            // Iterate through all factors in the context
            foreach ($context->factors->getElements() as $factor) {
                /** @var Factor $factor */
                // Get all suggestions for this factor
                $factorSuggestions = $factor->getFactorSuggestions();

                // Add each suggestion's unique key to our tracking array
                foreach ($factorSuggestions->getElements() as $suggestion) {
                    $uniqueSuggestions[$suggestion->uniqueKey()] = true;
                }
            }
        }

        // Return the count of unique suggestions
        return count($uniqueSuggestions);
    }
}
