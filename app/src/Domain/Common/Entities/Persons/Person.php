<?php

declare(strict_types=1);

namespace App\Domain\Common\Entities\Persons;

use App\Domain\Base\Entities\Translatable\Translatable;
use DDD\Domain\Base\Entities\ValueObject;

class Person extends ValueObject
{
    /** @var string|null The last name of the person */
    public ?string $lastName;

    /** @var string|null The first name of the person */
    public ?string $firstName;

    /** @var string|null Title that is used in the salutation */
    public ?string $title;

    /** @var string|null The academic title of the person */
    public ?string $academicTitle;

    /** @var string|null The job title of the person */
    public ?string $jobTitle;

    /** @var PersonGender The gender of the person */
    public PersonGender $gender;

    public function __construct()
    {
        $this->gender = new PersonGender();
        parent::__construct();
    }

    /**
     * @return string|null Returns full name in format <title> <firstName> <lastName>
     */
    public function getFullName(): ?string
    {
        $fullName = '';
        if (isset($this->title)) {
            $fullName .= $this->title;
        }
        if (isset($this->academicTitle)) {
            $fullName .= ($fullName ? ' ' : '') . $this->academicTitle;
        }
        if (isset($this->firstName)) {
            $fullName .= ($fullName ? ' ' : '') . $this->firstName;
        }
        if (isset($this->lastName)) {
            $fullName .= ($fullName ? ' ' : '') . $this->lastName;
        }
        return $fullName;
    }

    /**
     * @return string Returns saluation for mails and notifications
     */
    public function getSalutation(): string
    {
        if (Translatable::getCurrentWritingStyle() == Translatable::WRITING_STYLE_INFORMAL) {
            if (isset($this->firstName) && !empty($this->firstName)) {
                /* translators: %s is the person's first name */
                return sprintf(__('Hi %s', 'beyond-seo'), $this->firstName);
            }
        } elseif (isset($this->lastName) && !empty($this->lastName) && isset($this->firstName) && !empty($this->firstName)) {
            switch ($this->gender->getGender()) {
                case PersonGender::GENDER_MALE:
                    /* translators: %1$s is the first name, %2$s is the last name */
                    return sprintf(__('Dear Mr. %1$s %2$s', 'beyond-seo'), $this->firstName, $this->lastName);
                case PersonGender::GENDER_FEMALE:
                    /* translators: %1$s is the first name, %2$s is the last name */
                    return sprintf(__('Dear Mrs. %1$s %2$s', 'beyond-seo'), $this->firstName, $this->lastName);
            }
        }
        return __('Hi there', 'beyond-seo');
    }

    /**
     * @return array Returns various name combinations for matching purposes
     */
    public function getNameCombinations(): array
    {
        $combinations = [];
        $firstName = isset($this->firstName) ? trim(
            strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $this->firstName))
        ) : '';
        $lastName = isset($this->lastName) ? trim(strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $this->lastName))) : '';
        $lastName = str_replace('-', ' ', $lastName);
        // Split first names and generate combinations
        $lastNames = explode(' ', $lastName);
        $lastNames[] = $lastName;
        $lastNames = array_unique($lastNames);


        // Replace spaces with hyphens to standardize the delimiter
        $firstName = str_replace('-', ' ', $firstName);
        // Split first names and generate combinations
        $firstNames = explode(' ', $firstName);
        $firstNames[] = $firstName;
        $firstNames = array_unique($firstNames);

        foreach ($firstNames as $firstNameSegment){
            foreach ($lastNames as $lastNameSegment){
                // add combinations for <full firstname> <lastname>
                $combinations[] = trim($firstNameSegment . ' ' . $lastNameSegment);

                // add combinations for <full firstname> <lastname>
                $combinations[] = trim($lastNameSegment . ' ' . $firstNameSegment);
            }
        }
        return array_unique($combinations);
    }

}
