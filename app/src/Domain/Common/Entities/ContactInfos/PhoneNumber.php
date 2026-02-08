<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\ContactInfos;

use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Infrastructure\Validation\Constraints\Choice;

class PhoneNumber extends ContactInfo
{
    /** @var int The default format of the PhoneNumber */
    public const DEFAULT_FORMAT = 0;

    /** @var string The scope Phone of the PhoneNumber */
    public const SCOPE_PHONE = 'PHONE';

    /** @var string The scope Mobile Phone of the PhoneNumber */
    public const SCOPE_MOBILE_PHONE = 'MOBILEPHONE';

    /** @var string The scope Additional Phone of the PhoneNumber */
    public const SCOPE_ADDITIONAL_PHONE = 'ADDITIONALPHONE';

    /** @var string The scope Fax of the PhoneNumber */
    public const SCOPE_FAX = 'FAX';

    /** @var string Validates if number is valid */
    public const VALIDATION_EXACT = 'VALIDATION_EXACT';

    /** @var string Validates if number is a possible number, this is a lay validation */
    public const VALIDATION_POSSIBLE = 'VALIDATION_POSSIBLE';


    /** @var string|null Type of ContactInfo */
    #[Choice(choices: [self::TYPE_PHONE])]
    public ?string $type = self::TYPE_PHONE;

    #[Choice(choices: [self::SCOPE_PHONE, self::SCOPE_MOBILE_PHONE, self::SCOPE_ADDITIONAL_PHONE, self::SCOPE_FAX])]
    public ?string $scope;
    
    /** @var string|null The phone number itself */
    public ?string $value;

    /** @var string|null The countryShortCode of the Numbers country */
    public ?string $countryShortCode;

    /** @var string|null Validation level of the PhoneNumber */
    #[Choice(choices: [self::VALIDATION_EXACT, self::VALIDATION_POSSIBLE])]
    public ?string $validationLevel = self::VALIDATION_POSSIBLE;

    public function __construct(?string $phoneNumber = null, string $countryShortCode = null, $scope = self::SCOPE_PHONE)
    {
        if ($phoneNumber)
            $this->setAndNormalizePhoneNumber($phoneNumber, countryShortCode: $countryShortCode);
        if ($countryShortCode)
            $this->countryShortCode = $countryShortCode;
        $this->scope = $scope;
        return parent::__construct();
    }


    /**
     * Sets and normalizes the phone number
     * @param string $number
     * @param string $scope
     * @param string|null $countryShortCode
     * @return void
     */
    public function setAndNormalizePhoneNumber(string $number, string $scope = self::SCOPE_PHONE, string $countryShortCode = null): void
    {
        $this->countryShortCode = $countryShortCode;
        $this->scope = $scope;
        $this->value = trim($number);
    }

    /**
     * @param string|null $number
     * @return string|null
     */
    public static function convertToYextNumber(?string $number = null): ?string
    {
        if (!$number) {
            return null;
        }
        $finalNumber = substr($number, 1);
        return substr($finalNumber, 0, 2) . '-' . substr($finalNumber, 2, 3) . '-' . substr($finalNumber, 5);
    }

    /**
     * @param string $value
     * @param string|null $countryShortCode
     * @return void
     */
    public function normalizeAndSetValue(string $value, string $countryShortCode = null): void
    {
        $this->setAndNormalizePhoneNumber($value, countryShortCode: $countryShortCode);
    }

    /**
     * @param PhoneNumber|null $other
     * @return bool
     */
    public function isEqualTo(?DefaultObject $other = null): bool
    {
        return $this->value == $other->value && $this->scope == $other->scope;
    }
}
