<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Settings;

use App\Domain\Common\Entities\Settings\Setting;
use App\Domain\Integrations\WordPress\Seo\Repo\InternalDB\WebPages\Settings\InternalDBWPWebPageSetting;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use Exception;

/**
 * Class WPWebPageSetting
 */
#[LazyLoadRepo(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPWebPageSetting::class)]
class WPWebPageSetting extends Setting
{
    #============================================
    # region Attributes
    #============================================
    /** @var int|null $umetaId The ID of the content meta. */
    public ?int $cmetaId = null;

    /** @var int $contentId The ID of the content. */
    public int $contentId;

    /** @var string $metaKey The key of the content meta. */
    public string $metaKey;

    /** @var object $metaValue The value of the content meta. */
    public object $metaValue;

    /** @var string $uniqueKey The unique key of the content meta. */
    public string $uniqueKey;
    # endregion
    #============================================

    #============================================
    # region Constructor and Methods
    #============================================
    /**
     * @throws Exception
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Retrieves a unique key for the task.
     * @return string
     * @throws Exception
     */
    public function uniqueKey(): string
    {
        try {
            return self::uniqueKeyStatic(parent::uniqueKey() . '_' . spl_object_hash($this));
        } catch (Exception $e) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            throw new Exception('Failed to generate unique key: ' . $e->getMessage());
        }
    }
    # endregion
    #============================================
}
