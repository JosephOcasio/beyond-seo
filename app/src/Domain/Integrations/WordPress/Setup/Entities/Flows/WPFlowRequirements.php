<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows;

use DDD\Domain\Base\Entities\ValueObject;
use ReflectionClass;

/**
 * Class WPSetupRequirements
 */
class WPFlowRequirements extends ValueObject
{
    /**
     * All setup requirements
     */
    public const SETUP_REQUIREMENT_BUSINESS_EMAIL_ADDRESS = 'businessEmailAddress';
    public const SETUP_REQUIREMENT_BUSINESS_WEBSITE_URL = 'businessWebsiteUrl';
    public const SETUP_REQUIREMENT_BUSINESS_NAME = 'businessName';
    public const SETUP_REQUIREMENT_BUSINESS_DESCRIPTION = 'businessDescription';
    public const SETUP_REQUIREMENT_BUSINESS_ADDRESS = 'businessAddress';
    public const SETUP_REQUIREMENT_BUSINESS_GEO_ADDRESS = 'businessGeoAddress';
    public const SETUP_REQUIREMENT_BUSINESS_SERVICE_AREA = 'businessServiceArea';
    public const SETUP_REQUIREMENT_BUSINESS_KEYWORDS = 'businessKeywords';
    public const SETUP_REQUIREMENT_BUSINESS_CATEGORIES = 'businessCategories';
    public const SETUP_REQUIREMENT_BUSINESS_SPECIFIC_DESCRIPTION = 'businessSpecificDescription';

    /**
     * All setup steps based on requirements
     */
    public const SETUP_STEP_BUSINESS_SHORT_DESCRIPTION = [
        self::SETUP_REQUIREMENT_BUSINESS_WEBSITE_URL,
        self::SETUP_REQUIREMENT_BUSINESS_DESCRIPTION
    ];
    public const SETUP_STEP_BUSINESS_NAME = [
        self::SETUP_REQUIREMENT_BUSINESS_NAME
    ];
    public const SETUP_STEP_BUSINESS_DETAILED_DESCRIPTION = [
        self::SETUP_REQUIREMENT_BUSINESS_KEYWORDS,
        self::SETUP_REQUIREMENT_BUSINESS_CATEGORIES
    ];
    public const SETUP_STEP_BUSINESS_LOCATION_ADDRESS = [
        self::SETUP_REQUIREMENT_BUSINESS_ADDRESS
    ];
    public const SETUP_STEP_BUSINESS_SERVICE_AREA = [
        self::SETUP_REQUIREMENT_BUSINESS_SERVICE_AREA
    ];
    public const SETUP_STEP_BUSINESS_SPECIFIC_DESCRIPTION = [
        self::SETUP_REQUIREMENT_BUSINESS_DESCRIPTION,
        self::SETUP_REQUIREMENT_BUSINESS_KEYWORDS,
        self::SETUP_REQUIREMENT_BUSINESS_CATEGORIES
    ];

    /**
     * All setup collectors
     */
    public const SETUP_COLLECTOR_DATABASE = 'Database';
    public const SETUP_COLLECTOR_WORDPRESS = 'WordPress';
    public const SETUP_COLLECTOR_EXTENDIFY = 'Extendify';
    public const SETUP_COLLECTOR_RANKINGCOACH = 'RankingCoach';

    /**
     * All default questions, for each step
     */
    public const SETUP_QUESTION_WELCOME = 'Let\'s get started.';
    public const SETUP_QUESTION_BUSINESS_DESCRIPTION = 'First, could you tell me what your website or project is about?';
    public const SETUP_QUESTION_GREETING_1 = 'Awesome!';
    public const SETUP_QUESTION_BUSINESS_NAME = 'Do you already have a name for your website, project, or business?';
    public const SETUP_QUESTION_GREETING_2 = 'Wonderful!';
    public const SETUP_QUESTION_BUSINESS_DETAILED_DESCRIPTION = 'Could you describe in more detail what you plan to do with your website? For example, will you offer products or services, share blog articles, or something else?';
    public const SETUP_QUESTION_GREETING_3 = 'Just tasty! Thanks for sharing!';
    public const SETUP_QUESTION_BUSINESS_LOCATION_ADDRESS = 'Is your project or business tied to a specific location? Do you serve customers locally, or operate in multiple areas?';
    public const SETUP_QUESTION_GREETING_4 = 'I see.';
    public const SETUP_QUESTION_BUSINESS_SERVICE_AREA = 'Where do you primarily want to focus your reach? Is there a particular city or region you\'d like to target, or do you want to go nationwide?';
    public const SETUP_QUESTION_GREETING_5 = 'Thanks for providing that!';
    public const SETUP_QUESTION_BUSINESS_SPECIFIC_DESCRIPTION = 'Lastly, is there anything else you\'d like to highlight about your project or business, something that makes it unique or special?';


    /**
     * Config for each step's questions
     */
    public const SETUP_STEPS_QUESTIONS = [
        'SETUP_STEP_BUSINESS_SHORT_DESCRIPTION' => [
            self::SETUP_QUESTION_WELCOME,
            self::SETUP_QUESTION_BUSINESS_DESCRIPTION
        ],
        'SETUP_STEP_BUSINESS_NAME' => [
            self::SETUP_QUESTION_GREETING_1,
            self::SETUP_QUESTION_BUSINESS_NAME
        ],
        'SETUP_STEP_BUSINESS_DETAILED_DESCRIPTION' => [
            self::SETUP_QUESTION_GREETING_2,
            self::SETUP_QUESTION_BUSINESS_DETAILED_DESCRIPTION
        ],
        'SETUP_STEP_BUSINESS_LOCATION_ADDRESS' => [
            self::SETUP_QUESTION_GREETING_3,
            self::SETUP_QUESTION_BUSINESS_LOCATION_ADDRESS
        ],
        'SETUP_STEP_BUSINESS_SERVICE_AREA' => [
            self::SETUP_QUESTION_GREETING_4,
            self::SETUP_QUESTION_BUSINESS_SERVICE_AREA
        ],
        'SETUP_STEP_BUSINESS_SPECIFIC_DESCRIPTION' => [
            self::SETUP_QUESTION_GREETING_5,
            self::SETUP_QUESTION_BUSINESS_SPECIFIC_DESCRIPTION
        ]
    ];


    /**
     * Collect all requirements as array, used ReflectionAPI
     * @return array
     */
    public static function allRequirements(): array
    {
        $reflection = new ReflectionClass(__CLASS__);
        return array_filter($reflection->getConstants(), static function ($key) {
            return str_starts_with($key, 'SETUP_REQUIREMENT_');
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Check if a requirement is in allRequirements
     * @param string $requirement
     * @return bool
     */
    public static function hasRequirement(string $requirement): bool
    {
        return in_array($requirement, self::allRequirements(), true);
    }

    /**
     * Collect all requirements as array, used ReflectionAPI
     * @return array
     */
    public static function allSteps(): array
    {
        $reflection = new ReflectionClass(__CLASS__);
        return array_filter($reflection->getConstants(), static function ($key) {
            return str_starts_with($key, 'SETUP_STEP_');
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Collect all collectors as array, used ReflectionAPI
     * @return array
     */
    public static function allCollectors(): array
    {
        $reflection = new ReflectionClass(__CLASS__);
        return array_filter($reflection->getConstants(), static function ($key) {
            return str_starts_with($key, 'SETUP_COLLECTOR_');
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Collect all collectors as array, used ReflectionAPI
     * @return array
     */
    public static function allQuestions(): array
    {
        $reflection = new ReflectionClass(__CLASS__);
        return array_filter($reflection->getConstants(), static function ($key) {
            return str_starts_with($key, 'SETUP_QUESTION_');
        }, ARRAY_FILTER_USE_KEY);
    }
}
