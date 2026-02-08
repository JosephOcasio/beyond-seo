<?php

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords;

use App\Domain\Common\Entities\Keywords\Keywords;
use App\Domain\Integrations\WordPress\Seo\Services\WPKeywordsService;

/**
 * Class WPKeywords
 *
 * @method WPKeyword[] getElements()
 * @method WPKeyword|null first()
 * @method WPKeyword|null getByUniqueKey(string $uniqueKey)
 * @method WPKeywordsService getService()
 * @property WPKeyword[] $elements
 */
class WPKeywords extends Keywords
{
    public const SERVICE_NAME = WPKeywordsService::class;

    /**
     * Creates a new instance of WPKeywords from an array of Keyword entities.
     *
     * @param Keywords $keywordsArray An array of Keyword entities.
     * @return WPKeywords A new instance of WPKeywords containing the converted keywords.
     */
    public static function createFromKeywordArray(Keywords $keywordsArray): WPKeywords
    {
        $keywords = new self();
        foreach ($keywordsArray as $keyword) {
            $keywordObj = WPKeyword::createFromKeyword($keyword);
            $keywords->add($keywordObj);
        }
        return $keywords;
    }

    /**
     * Adds new keywords to the current onboarding keywords if they do not already exist.
     *
     * @param WPKeywords $currentOnboardingKeywords The current onboarding keywords.
     * @param WPKeywords $newKeywords The new keywords to add.
     * @return WPKeywords The updated onboarding keywords.
     */
    public static function addOnboardingKeywords(WPKeywords $currentOnboardingKeywords, Keywords $newKeywords): WPKeywords {

        $keywordMap = [];
        foreach ($newKeywords->getElements() as $keyword) {
            $keywordMap[$keyword->hash] = $keyword;
        }

        $wpKeywordMap = [];
        foreach ($currentOnboardingKeywords->getElements() as $index => $wpKeyword) {
            $wpKeywordMap[$wpKeyword->hash] = $index;

            //if (isset($keywordMap[$wpKeyword->hash])) {
            //    $currentOnboardingKeywords->elements[$index]->externalId = $keywordMap[$wpKeyword->hash]->id;
            //}
        }

        foreach ($newKeywords->getElements() as $keyword) {
            if (!isset($wpKeywordMap[$keyword->hash])) {
                $newWpKeyword = WPKeyword::createFromKeyword($keyword);
                $currentOnboardingKeywords->add($newWpKeyword);
            }
        }

        return $currentOnboardingKeywords;
    }
}
