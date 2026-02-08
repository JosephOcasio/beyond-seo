<?php

declare (strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\SetupStepCompletions;

use App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps\WPSetupStep;
use DDD\Domain\Base\Entities\Entity;
use DDD\Infrastructure\Base\DateTime\DateTime;

/**
 * @property WPSetupStepCompletions $parent
 */
//#[LazyLoadRepo(LazyLoadRepo::INTERNAL_DB, repoClass: InternalDBWPSetupStepCompletion::class)]
class WPSetupStepCompletion extends Entity
{
    /** @var int The id of the SetupStep, usually we use the step number */
    public int $setupStepId = 1;

    /** @var WPSetupStep|null The completed SetupStep */
    //#[LazyLoad(repoType: LazyLoadRepo::INTERNAL_DB)]
    public ?WPSetupStep $setupStep = null;

    /** @var string|null The answer of the setup completion */
    public ?string $answer = null;

    /** @var bool If true, the user have to continue to next step */
    public bool $approved = false;

    /** @var string|null The revised question to ask the user if is not approved */
    public ?string $revisedQuestion = null;

    /** @var DateTime|null The first time the SetupStep has been accessed */
    public ?DateTime $timeOfFirstAccess;

    /** @var DateTime|null The time SetupStep has been completed */
    public ?DateTime $timeOfCompletion;

//    /** @var int|string|null The id of the Account who accessed or completed the Step */
//    public int|string|null $accountId;
//
//    /** @var Account|null The Account who accessed or completed the Step */
//    public ?Account $account;

//    /** @var int|string|null The id of the Location for which the SetupStep has been accessed or completed */
//    public int|string|null $locationId;
//
//    /** @var Location The Location for which the SetupStep has been accessed or completed */
//    public ?Location $location;

    /**
     * Return the unique key for the entity
     *
     * @return string
     */
    public function uniqueKey(): string
    {
        $setupStepKey = $this->setupStep?->uniqueKey() ?? '';
        return md5(parent::uniqueKey()  . '_' . $setupStepKey . '_' . $this->content  . '_' . json_encode($this->approved) . '_' . $this->revisedQuestion);
    }
}