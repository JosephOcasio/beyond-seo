<?php /** @noinspection PhpComplexFunctionInspection */
/** @noinspection PhpFunctionCyclomaticComplexityInspection */
/** @noinspection PhpComplexClassInspection */
/** @noinspection PhpMissingParentCallCommonInspection */
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Operations\AnalyzeBacklinkProfile;

use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Attributes\SeoMeta;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Configuration\WeightConfiguration;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Enums\Suggestion;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Interfaces\OperationInterface;
use App\Domain\Integrations\WordPress\Seo\Entities\Optimiser\Base\Operation;
use App\Domain\Integrations\WordPress\Seo\Entities\WPSeoMajesticRefDomainItems;
use App\Domain\Integrations\WordPress\Seo\Repo\RC\Optimiser\RCMajesticRefDomainsCheck;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use Throwable;

/**
 * Class ReferringDomainsAnalysisOperation
 *
 * This class is responsible for analyzing the referring domains of a website.
 */
#[SeoMeta(
    name: 'Referring Domains Analysis',
    weight: WeightConfiguration::WEIGHT_REFERRING_DOMAINS_ANALYSIS_OPERATION,
    description: 'Analyzes the referring domains of a website to assess backlink quality, diversity, and relevance. Provides insights into the backlink profile including trust flow, citation flow, topical relevance, and geographic distribution.',
)]
class ReferringDomainsAnalysisOperation extends Operation implements OperationInterface
{
    // Thresholds for backlink quality assessment
    private const MIN_REFERRING_DOMAINS = 10;
    private const GOOD_REFERRING_DOMAINS = 50;
    private const EXCELLENT_REFERRING_DOMAINS = 100;
    
    private const MIN_TRUST_FLOW = 10;
    private const GOOD_TRUST_FLOW = 30;
    private const EXCELLENT_TRUST_FLOW = 50;
    
    private const MIN_CITATION_FLOW = 15;
    private const GOOD_CITATION_FLOW = 40;
    private const EXCELLENT_CITATION_FLOW = 60;
    
    private const MIN_TF_CF_RATIO = 0.5;
    private const GOOD_TF_CF_RATIO = 0.8;
    private const EXCELLENT_TF_CF_RATIO = 1.0;

    // Constants for link age analysis
    private const LINK_GROWTH_THRESHOLD = 0.1; // 10% growth rate

    /**
     * @var WPSeoMajesticRefDomainItems|null $majesticRefDomains
     * The Majestic Ref Domains check object.
     */
    #[HideProperty]
    public ?WPSeoMajesticRefDomainItems $majesticRefDomains = null;

    /**
     * Constructor for initializing the operation with a key.
     *
     * @param string $key The key for the operation
     * @param string $name The name of the operation
     * @param float $weight The weight of the operation
     * @throws Throwable
     */
    public function __construct(string $key, string $name, float $weight)
    {
        $this->majesticRefDomains = new WPSeoMajesticRefDomainItems();
        parent::__construct($key, $name, $weight);
    }

    /**
     * Performs the analysis of referring domains for the given post-ID.
     *
     * @return array|null The analysis results or null if the post-ID is invalid.
     * @throws InternalErrorException
     */
    public function run(): ?array
    {
        $postId = $this->postId;
        
        // Get the URL of the post
        $pageUrl = $this->contentProvider->getPostUrl($postId);
        
        if (empty($pageUrl)) {
            return [
                'success' => false,
                'message' => __('No valid URL found for this post', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }
        
        // Fetch referring domains data from Majestic API
        $this->analyseMajesticRefsDomains($pageUrl);
        
        // Check if we have valid data
        if (empty($this->majesticRefDomains) || count($this->majesticRefDomains->getElements()) === 0) {
            if(!$this->getFeatureFlag('external_api_call')) {
                return [
                    'success' => false,
                    'message' => __('External API call is disabled by feature flag', 'beyond-seo'),
                    'status' => [],
                    'recommendations' => [],
                    'feature_disabled' => true
                ];
            }

            return [
                'success' => false,
                'message' => __('Failed to fetch referring domains data from API', 'beyond-seo'),
                'status' => [],
                'recommendations' => []
            ];
        }
        
        // Calculate key metrics
        $metrics = $this->calculateBacklinkMetrics($this->majesticRefDomains->getElements());
        
        return [
            'success' => true,
            'message' => __('Referring domains analysis completed successfully', 'beyond-seo'),
            'status' => [
                'url' => $pageUrl,
                'total_referring_domains' => $metrics['total_referring_domains'],
                'total_backlinks' => $metrics['total_backlinks'],
                'avg_trust_flow' => $metrics['avg_trust_flow'],
                'avg_citation_flow' => $metrics['avg_citation_flow'],
                'tf_cf_ratio' => $metrics['tf_cf_ratio'],
                'topical_relevance_score' => $metrics['topical_relevance_score'],
                'domain_authority_distribution' => $metrics['domain_authority_distribution'],
                'link_velocity' => $metrics['link_velocity'],
                'geographic_distribution' => $metrics['geographic_distribution'],
                'top_countries' => $metrics['top_countries'],
                'top_tlds' => $metrics['top_tlds'],
                'top_topics' => $metrics['top_topics'],
                'top_referring_domains' => $metrics['top_referring_domains'],
                'link_age_distribution' => $metrics['link_age_distribution'],
                'link_growth_rate' => $metrics['link_growth_rate'],
                'language_distribution' => $metrics['language_distribution'],
                'outbound_link_analysis' => $metrics['outbound_link_analysis']
            ],
            'raw_data' => [
                'referring_domains' => $this->majesticRefDomains->getElements()
            ]
        ];
    }

    /**
     * Evaluate the operation value based on the Majestic Ref Domains data.
     *
     * @return float The calculated score based on the analysis.
     */
    public function calculateScore(): float
    {
        $statusData = $this->value['status'] ?? [];

        if (empty($statusData)) {
            return 0;
        }

        // Check if the feature is disabled
        if (isset($statusData['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return 0.5; // Return a neutral score when the feature is disabled
        }
        
        // Initialize component scores
        $quantityScore = 0;
        
        // Calculate quantity score (30% weight)
        $totalRefDomains = $statusData['total_referring_domains'] ?? 0;
        if ($totalRefDomains >= self::EXCELLENT_REFERRING_DOMAINS) {
            $quantityScore = 1.0;
        } elseif ($totalRefDomains >= self::GOOD_REFERRING_DOMAINS) {
            $quantityScore = 0.8;
        } elseif ($totalRefDomains >= self::MIN_REFERRING_DOMAINS) {
            $quantityScore = 0.5;
        }
        
        // Calculate quality score based on TF/CF metrics (40% weight)
        $avgTrustFlow = $statusData['avg_trust_flow'] ?? 0;
        $avgCitationFlow = $statusData['avg_citation_flow'] ?? 0;
        $tfCfRatio = $statusData['tf_cf_ratio'] ?? 0;
        
        $tfScore = 0;
        $cfScore = 0;
        $ratioScore = 0;
        
        if ($avgTrustFlow >= self::EXCELLENT_TRUST_FLOW) {
            $tfScore = 1.0;
        } elseif ($avgTrustFlow >= self::GOOD_TRUST_FLOW) {
            $tfScore = 0.8;
        } elseif ($avgTrustFlow >= self::MIN_TRUST_FLOW) {
            $tfScore = 0.5;
        }
        
        if ($avgCitationFlow >= self::EXCELLENT_CITATION_FLOW) {
            $cfScore = 1.0;
        } elseif ($avgCitationFlow >= self::GOOD_CITATION_FLOW) {
            $cfScore = 0.8;
        } elseif ($avgCitationFlow >= self::MIN_CITATION_FLOW) {
            $cfScore = 0.5;
        }
        
        if ($tfCfRatio >= self::EXCELLENT_TF_CF_RATIO) {
            $ratioScore = 1.0;
        } elseif ($tfCfRatio >= self::GOOD_TF_CF_RATIO) {
            $ratioScore = 0.8;
        } elseif ($tfCfRatio >= self::MIN_TF_CF_RATIO) {
            $ratioScore = 0.5;
        }
        
        $qualityScore = ($tfScore * 0.4) + ($cfScore * 0.3) + ($ratioScore * 0.3);
        
        // Calculate relevance score (15% weight)
        $relevanceScore = $statusData['topical_relevance_score'] ?? 0;
        
        // Calculate diversity score (15% weight)
        $domainAuthorityDistribution = $statusData['domain_authority_distribution'] ?? [];
        $geographicDistribution = $statusData['geographic_distribution'] ?? [];
        $languageDistribution = $statusData['language_distribution'] ?? [];
        
        $authorityDiversityScore = 0;
        $geoDiversityScore = 0;
        $languageDiversityScore = 0;
        
        // Check if we have a good distribution of domain authorities
        if (!empty($domainAuthorityDistribution)) {
            $hasHighAuthority = ($domainAuthorityDistribution['high'] ?? 0) > 0;
            $hasMediumAuthority = ($domainAuthorityDistribution['medium'] ?? 0) > 0;
            $hasLowAuthority = ($domainAuthorityDistribution['low'] ?? 0) > 0;
            
            if ($hasHighAuthority && $hasMediumAuthority && $hasLowAuthority) {
                $authorityDiversityScore = 1.0;
            } elseif (($hasHighAuthority && $hasMediumAuthority) || ($hasMediumAuthority && $hasLowAuthority)) {
                $authorityDiversityScore = 0.7;
            } else {
                $authorityDiversityScore = 0.3;
            }
        }
        
        // Check geographic diversity
        if (!empty($geographicDistribution)) {
            $geoDiversityScore = min(1.0, count($geographicDistribution) / 5);
        }
        
        // Check language diversity
        if (!empty($languageDistribution)) {
            $languageDiversityScore = min(1.0, count($languageDistribution) / 3);
        }
        
        $diversityScore = ($authorityDiversityScore * 0.5) + ($geoDiversityScore * 0.3) + ($languageDiversityScore * 0.2);
        
        // Calculate final score with weights
        return ($quantityScore * 0.3) + ($qualityScore * 0.4) + ($relevanceScore * 0.15) + ($diversityScore * 0.15);
    }

    /**
     * Generate suggestions based on the analysis results.
     *
     * @return array Active suggestions based on identified issues
     */
    public function suggestions(): array
    {
        $activeSuggestions = [];
        
        $statusData = $this->value['status'] ?? [];
        
        if (empty($statusData)) {
            return $activeSuggestions;
        }

        // Check if the feature is disabled
        if (isset($statusData['feature_disabled']) && $this->value['feature_disabled'] === true) {
            return $activeSuggestions; // Return no suggestions when the feature is disabled
        }
        
        // Check for low number of referring domains
        $totalRefDomains = $statusData['total_referring_domains'] ?? 0;
        if ($totalRefDomains < self::MIN_REFERRING_DOMAINS) {
            $activeSuggestions[] = Suggestion::INSUFFICIENT_REFERRING_DOMAINS;
        } elseif ($totalRefDomains < self::GOOD_REFERRING_DOMAINS) {
            $activeSuggestions[] = Suggestion::INCREASE_REFERRING_DOMAINS;
        }
        
        // Check for low trust flow
        $avgTrustFlow = $statusData['avg_trust_flow'] ?? 0;
        if ($avgTrustFlow < self::MIN_TRUST_FLOW) {
            $activeSuggestions[] = Suggestion::LOW_QUALITY_BACKLINKS;
        }
        
        // Check for poor TF/CF ratio
        $tfCfRatio = $statusData['tf_cf_ratio'] ?? 0;
        if ($tfCfRatio < self::MIN_TF_CF_RATIO) {
            $activeSuggestions[] = Suggestion::UNNATURAL_BACKLINK_PROFILE;
        }
        
        // Check for low topical relevance
        $topicalRelevance = $statusData['topical_relevance_score'] ?? 0;
        if ($topicalRelevance < 0.5) {
            $activeSuggestions[] = Suggestion::LOW_RELEVANCE_BACKLINKS;
        }
        
        // Check for poor domain authority distribution
        $domainAuthorityDistribution = $statusData['domain_authority_distribution'] ?? [];
        $highAuthorityCount = $domainAuthorityDistribution['high'] ?? 0;
        
        if ($highAuthorityCount === 0) {
            $activeSuggestions[] = Suggestion::MISSING_HIGH_AUTHORITY_BACKLINKS;
        }
        
        // Check for poor geographic distribution
        $geographicDistribution = $statusData['geographic_distribution'] ?? [];
        if (count($geographicDistribution) <= 1) {
            $activeSuggestions[] = Suggestion::POOR_BACKLINK_DIVERSITY;
        }
        
        // Check for negative link velocity
        $linkVelocity = $statusData['link_velocity'] ?? 0;
        if ($linkVelocity < 0) {
            $activeSuggestions[] = Suggestion::DECLINING_BACKLINK_PROFILE;
        }
        
        // Check for negative link growth rate
        $linkGrowthRate = $statusData['link_growth_rate'] ?? 0;
        if ($linkGrowthRate < self::LINK_GROWTH_THRESHOLD) {
            $activeSuggestions[] = Suggestion::DECLINING_BACKLINK_PROFILE;
        }
        
        return $activeSuggestions;
    }

    /**
     * Fetch the Majestic Ref Domains data.
     *
     * @param string $pageUrl
     * @return void
     * @throws InternalErrorException
     */
    private function analyseMajesticRefsDomains(string $pageUrl): void {

        // Check if external API call is enabled via feature flag
        if (!$this->getFeatureFlag('external_api_call')) {
            // Return empty array if external API call is disabled
            return;
        }

        // remove the scheme from the URL
        $pageUrl = preg_replace('/^https?:\/\//', '', $pageUrl);

        $rcRepo = new RCMajesticRefDomainsCheck();
        $rcRepo->urls = [$pageUrl];
        $rcRepo->count = 30; // Limit to 50 results
        $rcRepo->datasource = RCMajesticRefDomainsCheck::DATASOURCE_FRESH;
        $rcRepo->analysisDepth = 0;

        $rcRepo->setParent($this);
        $rcRepo->rcLoad(false, false);
    }
    
    /**
     * Calculate key backlink metrics from the referring domains data.
     *
     * @return array Calculated metrics
     */
    private function calculateBacklinkMetrics(array $refDomains): array
    {
        $totalDomains = count($refDomains);
        
        if ($totalDomains === 0) {
            return [
                'total_referring_domains' => 0,
                'total_backlinks' => 0,
                'avg_trust_flow' => 0,
                'avg_citation_flow' => 0,
                'tf_cf_ratio' => 0,
                'topical_relevance_score' => 0,
                'domain_authority_distribution' => [
                    'high' => 0,
                    'medium' => 0,
                    'low' => 0
                ],
                'link_velocity' => 0,
                'geographic_distribution' => [],
                'top_countries' => [],
                'top_tlds' => [],
                'top_topics' => [],
                'top_referring_domains' => [],
                'link_age_distribution' => [
                    'new' => 0,
                    'recent' => 0,
                    'established' => 0,
                    'old' => 0
                ],
                'link_growth_rate' => 0,
                'language_distribution' => [],
                'outbound_link_analysis' => [
                    'avg_external_outlinks' => 0,
                    'avg_internal_outlinks' => 0,
                    'avg_outlink_ratio' => 0
                ]
            ];
        }
        
        // Calculate average Trust Flow and Citation Flow
        $totalTrustFlow = 0;
        $totalCitationFlow = 0;
        $totalBacklinks = 0;
        $highAuthorityCount = 0;
        $mediumAuthorityCount = 0;
        $lowAuthorityCount = 0;
        $countryDistribution = [];
        $tldDistribution = [];
        $languageDistribution = [];
        $topicalDistribution = [];
        $topicalRelevanceScore = 0;
        $recentLinksCount = 0;
        $oldLinksCount = 0;
        $totalExternalOutlinks = 0;
        $totalInternalOutlinks = 0;
        
        // Link age tracking
        $newLinks = 0; // < 3 months
        $recentLinks = 0; // 3-6 months
        $establishedLinks = 0; // 6-12 months
        $oldLinks = 0; // > 12 months

        $threeMonthsAgo = strtotime('-3 months');
        $sixMonthsAgo = strtotime('-6 months');
        $oneYearAgo = strtotime('-1 year');
        
        // Sort domains by position for top referring domains
        usort($refDomains, function($a, $b) {
            return $a->Position <=> $b->Position;
        });
        
        // Prepare top referring domains array
        $topReferringDomains = [];
        $count = 0;
        
        foreach ($refDomains as $domain) {
            // Track total backlinks
            $totalBacklinks += $domain->BackLinks ?? 0;
            
            // Track TF/CF metrics
            $totalTrustFlow += $domain->TrustFlow;
            $totalCitationFlow += $domain->CitationFlow;
            
            // Categorize by domain authority
            if ($domain->TrustFlow >= 40) {
                $highAuthorityCount++;
            } elseif ($domain->TrustFlow >= 20) {
                $mediumAuthorityCount++;
            } else {
                $lowAuthorityCount++;
            }
            
            // Track geographic distribution
            if (!empty($domain->CountryCode)) {
                $countryDistribution[$domain->CountryCode] = ($countryDistribution[$domain->CountryCode] ?? 0) + 1;
            }
            
            // Track TLD distribution
            if (!empty($domain->TLD)) {
                $tldDistribution[$domain->TLD] = ($tldDistribution[$domain->TLD] ?? 0) + 1;
            }
            
            // Track language distribution
            if (!empty($domain->Language)) {
                $languageDistribution[$domain->Language] = ($languageDistribution[$domain->Language] ?? 0) + 1;
            }
            
            // Calculate topical relevance
            // Collect all topical trust flow topics and values
            for ($i = 0; $i <= 9; $i++) {
                $topicProperty = "TopicalTrustFlow_Topic_$i";
                $valueProperty = "TopicalTrustFlow_Value_$i";
                
                if (!empty($domain->{$topicProperty}) && $domain->{$valueProperty} > 0) {
                    $topic = $domain->{$topicProperty};
                    $value = $domain->{$valueProperty};
                    
                    if (!isset($topicalDistribution[$topic])) {
                        $topicalDistribution[$topic] = [
                            'count' => 0,
                            'total_value' => 0,
                            'avg_value' => 0
                        ];
                    }
                    
                    $topicalDistribution[$topic]['count']++;
                    $topicalDistribution[$topic]['total_value'] += $value;
                }
            }
            
            // Use the highest topical trust flow value as an indicator of relevance
            $topicalValues = [
                $domain->TopicalTrustFlow_Value_0 ?? 0,
                $domain->TopicalTrustFlow_Value_1 ?? 0,
                $domain->TopicalTrustFlow_Value_2 ?? 0,
                $domain->TopicalTrustFlow_Value_3 ?? 0,
                $domain->TopicalTrustFlow_Value_4 ?? 0
            ];
            $maxTopicalValue = max($topicalValues);
            $topicalRelevanceScore += ($maxTopicalValue / 100); // Normalize to 0-1 scale
            
            // Track link age for velocity calculation
            if (!empty($domain->LastLinkDate)) {
                $lastLinkDate = strtotime($domain->LastLinkDate);
                if ($lastLinkDate) {
                    if ($lastLinkDate > $threeMonthsAgo) {
                        $newLinks++;
                        $recentLinksCount++;
                    } elseif ($lastLinkDate > $sixMonthsAgo) {
                        $recentLinks++;
                        $recentLinksCount++;
                    } elseif ($lastLinkDate > $oneYearAgo) {
                        $establishedLinks++;
                        $oldLinksCount++;
                    } else {
                        $oldLinks++;
                        $oldLinksCount++;
                    }
                }
            }
            
            // Track outbound links
            $totalExternalOutlinks += $domain->OutLinksExternal ?? 0;
            $totalInternalOutlinks += $domain->OutLinksInternal ?? 0;
            
            // Add to top referring domains (limit to top 10)
            if ($count < 10) {
                $topReferringDomains[] = [
                    'domain' => $domain->Domain,
                    'trust_flow' => $domain->TrustFlow,
                    'citation_flow' => $domain->CitationFlow,
                    'backlinks' => $domain->BackLinks ?? 0,
                    'country_code' => $domain->CountryCode ?? '',
                    'language' => $domain->LanguageDesc ?? '',
                    'first_link_date' => $domain->FirstLinkDate ?? '',
                    'last_link_date' => $domain->LastLinkDate ?? ''
                ];
                $count++;
            }
        }
        
        // Calculate averages and ratios
        $avgTrustFlow = $totalTrustFlow / $totalDomains;
        $avgCitationFlow = $totalCitationFlow / $totalDomains;
        $tfCfRatio = $avgCitationFlow > 0 ? $avgTrustFlow / $avgCitationFlow : 0;
        $avgTopicalRelevance = $topicalRelevanceScore / $totalDomains;
        
        // Calculate link velocity (positive if gaining links, negative if losing)
        $linkVelocity = $totalDomains > 0 ? ($recentLinksCount - $oldLinksCount) / $totalDomains : 0;
        
        // Calculate link growth rate
        $totalOlderLinks = $establishedLinks + $oldLinks;
        $totalNewerLinks = $newLinks + $recentLinks;
        $linkGrowthRate = $totalOlderLinks > 0 ? $totalNewerLinks / $totalOlderLinks : 0;
        
        // Calculate average outbound link metrics
        $avgExternalOutlinks = $totalDomains > 0 ? $totalExternalOutlinks / $totalDomains : 0;
        $avgInternalOutlinks = $totalDomains > 0 ? $totalInternalOutlinks / $totalDomains : 0;
        $avgOutlinkRatio = $totalInternalOutlinks > 0 ? $totalExternalOutlinks / $totalInternalOutlinks : 0;
        
        // Sort country distribution by count and get top 5
        arsort($countryDistribution);
        $topCountries = array_slice($countryDistribution, 0, 5, true);
        
        // Sort TLD distribution by count and get top 5
        arsort($tldDistribution);
        $topTlds = array_slice($tldDistribution, 0, 5, true);
        
        // Calculate average values for topical distribution and sort by count
        foreach ($topicalDistribution as $topic => $data) {
            $topicalDistribution[$topic]['avg_value'] = $data['count'] > 0 ? 
                $data['total_value'] / $data['count'] : 0;
        }
        
        // Sort topical distribution by count and get top 5
        uasort($topicalDistribution, function($a, $b) {
            return $b['count'] <=> $a['count'];
        });
        
        $topTopics = [];
        $count = 0;
        foreach ($topicalDistribution as $topic => $data) {
            if ($count >= 5) break;
            $topTopics[$topic] = $data;
            $count++;
        }
        
        return [
            'total_referring_domains' => $totalDomains,
            'total_backlinks' => $totalBacklinks,
            'avg_trust_flow' => $avgTrustFlow,
            'avg_citation_flow' => $avgCitationFlow,
            'tf_cf_ratio' => $tfCfRatio,
            'topical_relevance_score' => $avgTopicalRelevance,
            'domain_authority_distribution' => [
                'high' => $highAuthorityCount,
                'medium' => $mediumAuthorityCount,
                'low' => $lowAuthorityCount
            ],
            'link_velocity' => $linkVelocity,
            'geographic_distribution' => $countryDistribution,
            'top_countries' => $topCountries,
            'top_tlds' => $topTlds,
            'top_topics' => $topTopics,
            'top_referring_domains' => $topReferringDomains,
            'link_age_distribution' => [
                'new' => $newLinks,
                'recent' => $recentLinks,
                'established' => $establishedLinks,
                'old' => $oldLinks
            ],
            'link_growth_rate' => $linkGrowthRate,
            'language_distribution' => $languageDistribution,
            'outbound_link_analysis' => [
                'avg_external_outlinks' => $avgExternalOutlinks,
                'avg_internal_outlinks' => $avgInternalOutlinks,
                'avg_outlink_ratio' => $avgOutlinkRatio
            ]
        ];
    }
}
