<?php

namespace App\Domain\Integrations\WordPress\Plugin\Services;

use App\Domain\Integrations\WordPress\Plugin\Entities\WPPlugin;
use App\Domain\Integrations\WordPress\Seo\Entities\WebPages\Content\Elements\ContentAnalysis\Keywords\WPKeywords;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Onboarding\Onboarding;
use App\Domain\Integrations\WordPress\Setup\Entities\WPSetup;
use DDD\Domain\Base\Services\EntitiesService;
use DDD\Infrastructure\Exceptions\BadRequestException;
use DDD\Infrastructure\Exceptions\InternalErrorException;
use Doctrine\ORM\Mapping\MappingException;
use Exception;
use Psr\Cache\InvalidArgumentException;
use RankingCoach\Inc\Core\Base\BaseConstants;
use RankingCoach\Inc\Core\Base\Traits\RcLoggerTrait;
use ReflectionException;
use RuntimeException;

/**
 * Service for WPPlugin entities.
 *
 * @method static WPPlugin getEntityClassInstance()
 */
class WPPluginService extends EntitiesService
{
    use RcLoggerTrait;

    /** @var string DEFAULT_ENTITY_CLASS The default entity class. */
    public const DEFAULT_ENTITY_CLASS = WPPlugin::class;

    /**
     * Retrieves the WPPlugin entity
     *
     * @return WPPlugin The WPPlugin entity.
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public function getPlugin(): WPPlugin
    {
        $plugin = new WPPlugin();
        $plugin->website;
        $plugin->setupData;
        return $plugin;
    }

    /**
     * Save the plugin onboarding data.
     *
     * @param Onboarding|null $onboarding
     * @return WPSetup
     * @throws BadRequestException
     * @throws InternalErrorException
     * @throws InvalidArgumentException
     * @throws MappingException
     * @throws ReflectionException
     */
    public function finalizeOnboardingState(Onboarding $onboarding = null): WPSetup
    {
        if (!$onboarding) {
            $this->getOnboardingData();
        }

        // Save keywords
        $wpService = (new WPKeywords())->getService();
        $wpService->addOnboardingKeywords($onboarding->keywords);

        $this->saveOnboardingComplete($onboarding->setupSettings?->rankingcoachCompleted ?? false);
        $this->saveApplicationOnboardingData();
        $this->savePluginOnboardingData();

        return $this->getOnboardingData();
    }

    /**
     * Get the plugin onboarding data.
     *
     * @return WPSetup
     */
    public function getOnboardingData(): WPSetup
    {
        $data = new WPPlugin();
        return $data->setupData;
    }

    /**
     * Save the internal onboarding data.
     */
    protected function savePluginOnboardingData(): void
    {
        update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP, true);
        update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_WP_LAST_UPDATE, time());
    }

    /**
     * Save the external onboarding data.
     */
    protected function saveApplicationOnboardingData(): void
    {
        update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC, true);
        update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_ON_RC_LAST_UPDATE, time());
    }

    /**
     * Save the onboarding completion status.
     */
    protected function saveOnboardingComplete(bool $completed = false): void
    {
        update_option(BaseConstants::OPTION_ACCOUNT_ONBOARDING_COMPLETED, $completed);
    }

    /**
     * Get the rankingCoach project ID.
     *
     * @return int|null The rankingCoach project ID or null if not set.
     */
    public function getRankingCoachAccountId(): ?int
    {
        return get_option(BaseConstants::OPTION_RANKINGCOACH_ACCOUNT_ID, null);
    }

    /**
     * Get the rankingCoach project ID.
     *
     * @return int|null The rankingCoach project ID or null if not set.
     */
    public function getRankingCoachProjectId(): ?int
    {
        return get_option(BaseConstants::OPTION_RANKINGCOACH_PROJECT_ID, null);
    }

    /**
     * Get the rankingCoach activation code.
     *
     * @return string|null The rankingCoach activation code or null if not set.
     */
    public function getRankingCoachActivationCode(): ?string
    {
        return get_option(BaseConstants::OPTION_ACTIVATION_CODE, null);
    }

    /**
     * Get the rankingCoach location ID.
     *
     * @return string|null The rankingCoach location ID or null if not set.
     */
    public function getRankingCoachSubscription(): ?string
    {
        return get_option(BaseConstants::OPTION_RANKINGCOACH_SUBSCRIPTION, null);
    }

    /**
     * Execute the callback exactly $repeating times
     *
     * @param int $repeating
     * @param callable $callback
     * @return void
     */
    public function retryOnboardingProcessing(int $repeating, callable $callback): void
    {
        if ($repeating <= 0) {
            throw new RuntimeException('The repeating parameter must be greater than 0.');
        }

        for ($i = 0; $i < $repeating; $i++) {
            $callback();
        }
    }

    /**
     * Get the number of remaining keywords for the rankingCoach account.
     *
     * @return int The number of remaining keywords.
     */
    public function getRankingCoachRemainingKeywords(): int
    {
        $remainingKeywords = get_option(BaseConstants::OPTION_SYNC_KEYWORDS_REMAINS_KEYWORDS, null);
        if ($remainingKeywords === null) {
            return 99999;
        }

        return (int)$remainingKeywords;
    }
}
