<?php

declare (strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\SetupSteps;

use DDD\Domain\Base\Entities\Entity;

/**
 * @property WPSetupSteps $parent
 */
class WPSetupStep extends Entity
{

    /** @var int The id of the SetupStep */
    public int $stepNumber = 1;

    /** @var string Name of the setup step */
    public string $reason;

    /** @var string|null The greeting to the user */
    public ?string $greeting = null;

    /** @var string|null The question to ask the user */
    public ?string $question;

    /** @var bool If true, this step marks the final step of a SetupSequence, when completing this step, the Sequence is completed */
    public bool $isFinalStep = false;

    /**
     * @param int $stepNumber
     * @param string $reason
     * @param string|null $greeting
     * @param string|null $question
     */
    public function __construct(int $stepNumber = 1, string $reason = '', ?string $greeting = null, ?string $question = null)
    {
        $this->stepNumber = $stepNumber;
        $this->reason = $reason;
        $this->greeting = $greeting;
        $this->question = $question;
        parent::__construct();
    }

    /**
     * Return the unique key for the entity
     *
     * @return string
     */
    public function uniqueKey(): string
    {
        return md5(parent::uniqueKey()  . '_' . $this->stepNumber  . '_' . $this->reason . '_' . $this->question);
    }
}