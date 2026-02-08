<?php

declare(strict_types=1);

namespace App\Domain\Common\Services;

use App\Domain\Common\Repo\InternalDB\Categories\InternalDBWPCategories;
use App\Domain\Integrations\WordPress\Common\Entities\Categories\WPCategories;
use App\Domain\Integrations\WordPress\Common\Entities\Categories\WPCategory;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use DDD\Infrastructure\Services\Service;
use Doctrine\ORM\Mapping\MappingException;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use ReflectionException;

/**
 * @method static WPCategories getEntityClassInstance()
 */
class WPCategoriesService extends Service
{
    public const DEFAULT_ENTITY_CLASS = WPCategory::class;

    /**
     * @throws MappingException
     * @throws BadRequestException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function searchDBCategories(string $searchString): ?WPCategories
    {
        return (new InternalDBWPCategories())->getCategories($searchString);
    }

    /**
     * @param string $searchString
     * @return WPCategories|null
     */
    public function searchCategories(string $searchString): ?WPCategories
    {
        $locale = WordpressHelpers::current_language_code_helper();
        $translatedCategories = rc_get_translated_categories($locale);
        $wpCategories = new WPCategories();
        if (empty($translatedCategories)) {
            return $wpCategories;
        }
        $filteredCategories = array_filter($translatedCategories, static function ($category) use ($searchString) {
            return stripos($category['name'], $searchString) !== false;
        });

        foreach ($filteredCategories as $category) {
            $wpCategory = WPCategory::fromArray($category);
            $wpCategories->add($wpCategory);
        }
        return $wpCategories;

    }

    /**
     * @throws MappingException
     * @throws InvalidArgumentException
     * @throws BadRequestException
     * @throws ReflectionException
     * @throws InternalErrorException
     */
    public function getDBCategoriesByName(...$name): ?WPCategories
    {
        return (new InternalDBWPCategories())->getCategoriesByName(...$name);
    }

    /**
     * Get categories by their names
     *
     * @param string ...$names
     * @return WPCategories|null
     */
    public function getCategoriesByName(...$names): ?WPCategories
    {
        $locale = WordpressHelpers::current_language_code_helper();
        $translatedByName = rc_get_translated_categories($locale, 'name');
        $wpCategories = new WPCategories();

        if (empty($translatedByName)) {
            return $wpCategories;
        }

        foreach ($names as $name) {
            $name = ucfirst(strtolower($name));
            if (!isset($translatedByName[$name])) {
                continue;
            }

            $category = $translatedByName[$name];
            $wpCategory = WPCategory::fromArray($category);
            $wpCategories->add($wpCategory);
        }

        return $wpCategories;
    }

    /**
     * Get categories by their IDs
     *
     * @param int ...$id
     * @return WPCategories|null
     */
    public function getCategoriesByCategoryId(...$id): ?WPCategories
    {
        // Load translated categories keyed by ID for current locale
        $language = WordpressHelpers::current_language_code_helper();
        $translatedCategories = rc_get_translated_categories($language);
        $wpCategories = new WPCategories();

        if (empty($translatedCategories)) {
            return $wpCategories;
        }

        foreach ($id as $categoryId) {
            $key = (int)$categoryId;
            if (!isset($translatedCategories[$key])) {
                continue;
            }

            $category = $translatedCategories[$key];
            $wpCategory = WPCategory::fromArray($category);
            $wpCategories->add($wpCategory);
        }

        return $wpCategories;
    }
}
