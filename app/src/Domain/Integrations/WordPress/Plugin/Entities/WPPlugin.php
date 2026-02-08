<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Plugin\Entities;

use App\Domain\Integrations\WordPress\Seo\Entities\Websites\WPWebsite;
use App\Domain\Integrations\WordPress\Setup\Entities\WPSetup;
use App\Domain\Integrations\WordPress\Setup\Repo\InternalDB\InternalDBWPSetup;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Base\DateTime\DateTime;
use Exception;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Helpers\WordpressHelpers;
use RankingCoach\Inc\Core\PluginSettings;
use WP_Debug_Data;

/**
 * Class WPPlugin
 */
class WPPlugin extends ValueObject
{
    /** @var string|null $name The version of the plugin */
    public ?string $version = null;

    /** @var object|null $settings */
    public ?object $settings = null;

    /** @var WPWebsite $website The website that the plugin is installed on */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB)]
    public WPWebsite $website;

    /**
     * @var WPSetup|null $setupData The plugin onboarding data, including the internal and external onboarding status
     */
    #[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPSetup::class)]
    public ?WPSetup $setupData = null;

    /** @var string|null $installedAt Time when the RC plugin was installed */
    public ?string $installedAt = null;

    /** @var DateTime $installedDateTime Time when the RC plugin was installed */
    public DateTime $installedDateTime;

    /** @var string|null $installationHash Hash of the installation */
    public ?string $installationHash = null;

    /** @var object|null $debugData Debug data for the plugin */
    public ?object $debugData = null;

    /**
     * Plugin constructor.
     *
     * @throws Exception
     *
     */
    public function __construct()
    {
        $this->version = get_option(BaseConstants::OPTION_PLUGIN_VERSION, null);
        $this->settings = (object)PluginSettings::instance(true)->get_all();
        $this->installationHash = get_option(BaseConstants::OPTION_INSTALLATION_ID, null);
        $this->installedAt = get_option(BaseConstants::OPTION_INSTALLATION_DATE, (string) (new DateTime())->getTimestamp());
        // Ensure that the WordPress admin includes are loaded
        WordpressHelpers::ensureWpAdminIncludesLoaded();
        /**
         * @TODO Implement database lazy loading if needed.
         */
//        $this->debugData = (object)WP_Debug_Data::debug_data();

        if (is_numeric($this->installedAt)) {
            $this->installedDateTime = (new DateTime())->setTimestamp((int) $this->installedAt);
        } elseif (strtotime($this->installedAt) !== false) {
            $this->installedDateTime = new DateTime($this->installedAt);
        } else {
            $this->installedDateTime = new DateTime();
        }

        parent::__construct();
    }
}
