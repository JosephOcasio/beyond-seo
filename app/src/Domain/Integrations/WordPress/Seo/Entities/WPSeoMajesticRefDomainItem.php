<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class RefDomainItem
 * Represents a single reference domain item from Majestic API
 */
class WPSeoMajesticRefDomainItem extends ValueObject
{
    /** @var int $Position The position in the results */
    public int $Position = 0;

    /** @var string $Domain The domain name */
    public string $Domain = '';

    /** @var int $RefDomains Number of referring domains */
    public int $RefDomains = 0;

    /** @var int|null $AlexaRank Alexa rank */
    public ?int $AlexaRank = null;

    /** @var int $Matches Number of matches */
    public int $Matches = 0;

    /** @var int $MatchedLinks Number of matched links */
    public int $MatchedLinks = 0;

    /** @var int $ExtBackLinks Number of external backlinks */
    public int $ExtBackLinks = 0;

    /** @var int $IndexedURLs Number of indexed URLs */
    public int $IndexedURLs = 0;

    /** @var int $CrawledURLs Number of crawled URLs */
    public int $CrawledURLs = 0;

    /** @var string|null $FirstCrawled Date of first crawl */
    public ?string $FirstCrawled = null;

    /** @var string|null $LastSuccessfulCrawl Date of last successful crawl */
    public ?string $LastSuccessfulCrawl = null;

    /** @var string|null $IP IP address */
    public ?string $IP = null;

    /** @var string|null $SubNet Subnet */
    public ?string $SubNet = null;

    /** @var string|null $CountryCode Country code */
    public ?string $CountryCode = null;

    /** @var string|null $TLD Top-level domain */
    public ?string $TLD = null;

    /** @var int $CitationFlow Citation flow score */
    public int $CitationFlow = 0;

    /** @var int $TrustFlow Trust flow score */
    public int $TrustFlow = 0;

    /** @var string|null $Title Page title */
    public ?string $Title = null;

    /** @var int $OutDomainsExternal Number of external outbound domains */
    public int $OutDomainsExternal = 0;

    /** @var int $OutLinksExternal Number of external outbound links */
    public int $OutLinksExternal = 0;

    /** @var int $OutLinksInternal Number of internal outbound links */
    public int $OutLinksInternal = 0;

    /** @var int $OutLinksPages Number of outbound links pages */
    public int $OutLinksPages = 0;

    /** @var string|null $Language Language code */
    public ?string $Language = null;

    /** @var string|null $LanguageDesc Language description */
    public ?string $LanguageDesc = null;

    /** @var string|null $LanguageConfidence Language confidence score */
    public ?string $LanguageConfidence = null;

    /** @var string|null $LanguagePageRatios Language page ratios */
    public ?string $LanguagePageRatios = null;

    /** @var int $LanguageTotalPages Total pages in language */
    public int $LanguageTotalPages = 0;

    /** @var int $LinkSaturation Link saturation */
    public int $LinkSaturation = 0;

    /** @var string|null $TopicalTrustFlow_Topic_0 Topical trust flow topic 0 */
    public ?string $TopicalTrustFlow_Topic_0 = null;

    /** @var int $TopicalTrustFlow_Value_0 Topical trust flow value 0 */
    public int $TopicalTrustFlow_Value_0 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_1 Topical trust flow topic 1 */
    public ?string $TopicalTrustFlow_Topic_1 = null;

    /** @var int $TopicalTrustFlow_Value_1 Topical trust flow value 1 */
    public int $TopicalTrustFlow_Value_1 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_2 Topical trust flow topic 2 */
    public ?string $TopicalTrustFlow_Topic_2 = null;

    /** @var int $TopicalTrustFlow_Value_2 Topical trust flow value 2 */
    public int $TopicalTrustFlow_Value_2 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_3 Topical trust flow topic 3 */
    public ?string $TopicalTrustFlow_Topic_3 = null;

    /** @var int $TopicalTrustFlow_Value_3 Topical trust flow value 3 */
    public int $TopicalTrustFlow_Value_3 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_4 Topical trust flow topic 4 */
    public ?string $TopicalTrustFlow_Topic_4 = null;

    /** @var int $TopicalTrustFlow_Value_4 Topical trust flow value 4 */
    public int $TopicalTrustFlow_Value_4 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_5 Topical trust flow topic 5 */
    public ?string $TopicalTrustFlow_Topic_5 = null;

    /** @var int $TopicalTrustFlow_Value_5 Topical trust flow value 5 */
    public int $TopicalTrustFlow_Value_5 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_6 Topical trust flow topic 6 */
    public ?string $TopicalTrustFlow_Topic_6 = null;

    /** @var int $TopicalTrustFlow_Value_6 Topical trust flow value 6 */
    public int $TopicalTrustFlow_Value_6 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_7 Topical trust flow topic 7 */
    public ?string $TopicalTrustFlow_Topic_7 = null;

    /** @var int $TopicalTrustFlow_Value_7 Topical trust flow value 7 */
    public int $TopicalTrustFlow_Value_7 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_8 Topical trust flow topic 8 */
    public ?string $TopicalTrustFlow_Topic_8 = null;

    /** @var int $TopicalTrustFlow_Value_8 Topical trust flow value 8 */
    public int $TopicalTrustFlow_Value_8 = 0;

    /** @var string|null $TopicalTrustFlow_Topic_9 Topical trust flow topic 9 */
    public ?string $TopicalTrustFlow_Topic_9 = null;

    /** @var int $TopicalTrustFlow_Value_9 Topical trust flow value 9 */
    public int $TopicalTrustFlow_Value_9 = 0;

    /** @var int|null $BackLinks_aradon_ro Backlinks */
    public ?int $BackLinks = null;

    /** @var string|null $FirstLinkDate_aradon_ro First link date */
    public ?string $FirstLinkDate = null;

    /** @var string|null $LastLinkDate_aradon_ro Last link date */
    public ?string $LastLinkDate = null;
}