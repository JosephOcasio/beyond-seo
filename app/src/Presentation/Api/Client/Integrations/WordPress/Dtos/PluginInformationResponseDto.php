<?php
declare(strict_types=1);

namespace App\Presentation\Api\Client\Integrations\WordPress\Dtos;

use App\Domain\Integrations\WordPress\Common\Entities\Accounts\Legacy\WPLegacyAccount;
use App\Domain\Integrations\WordPress\Plugin\Entities\WPPlugin;
use DDD\Presentation\Base\Dtos\RestResponseDto;

/**
 * Class PluginInformationResponseDto
 *
 * A data transfer object (DTO) to encapsulate the response structure of the `rankingcoach_plugin_information` API method.
 * @property string[] $settingsData
 * @property string[] $siteData
 * @property string[] $usersData
 * @property string[] $contentData
 * @property PluginCurrentUserDataResponseDto $currentUserData
 */
class PluginInformationResponseDto extends RestResponseDto {

	/**
	 * @var WPPlugin|null $pluginData The plugin data including the onboarding, settings, site
	 */
	public ?WPPlugin $pluginData = null;

	/**
	 * @var WPLegacyAccount|null $currentUserData The current user data including the user ID, email, and role
	 */
	public ?WPLegacyAccount $userData = null;

    /**
     * @var int|null $rcAccountID The rankingCoach account ID associated with the plugin
     */
    public ?int $rcAccountId = null;

    /**
     * @var int|null $rcProjectID The rankingCoach project ID associated with the plugin
     */
    public ?int $rcProjectId = null;

    /**
     * @var string|null $subscription The name of the rankingCoach subscription associated with the plugin
     */
    public ?string $rcSubscriptionName = null;

    /**
     * @var string|null $rcActivationCode The activation code used for the rankingCoach account
     */
    public ?string $rcActivationCode = null;

    /**
     * @var int $rcRemainingKeywords The number of remaining keywords available in the rankingCoach account
     */
    public int $rcRemainingKeywords = 99999;
}
