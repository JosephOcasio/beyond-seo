<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Persons;

use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Validation\Constraints\Choice;

class PersonGender extends ValueObject
{
    /** @var string Male gender */
    public const GENDER_MALE = 'm';

    /** @var string Female gender */
    public const GENDER_FEMALE = 'f';

    /** @var string Other gender */
    public const GENDER_OTHER = 'd';

    /** @var string All gender */
    public const GENDER_ALL = 'a';

    /** @var string|null The persons gender */
    #[Choice(choices: [self::GENDER_MALE, self::GENDER_FEMALE, self::GENDER_OTHER, self::GENDER_ALL], message: 'persongender.gender.choice')]
    public ?string $gender = 'm';

    public function __construct(?string $gender = null)
    {
        $this->setGender($gender);
        parent::__construct();
    }

    public function getGender(): string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): void
    {
        switch ($gender) {
            case self::GENDER_MALE:
                $this->gender = self::GENDER_MALE;
                break;
            case self::GENDER_FEMALE:
                $this->gender = self::GENDER_FEMALE;
                break;
            default:
                $this->gender = $gender;
        }
    }
}