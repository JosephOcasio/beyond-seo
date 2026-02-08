<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\Domains;

use App\Domain\Seo\Entities\WebPages\WebPage;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use DDD\Infrastructure\Validation\Constraints\Choice;

/**
 * @property Domain|WebPage $parent
 * @method Domain|WebPage getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusDomainValidation::class)]
class DomainValidation extends ValueObject
{
    public const VALIDATION_MODE_HOST = 'host';
    public const VALIDATION_MODE_FULL = 'full';

    /** @var string|null The status of the domain validation */
    #[HideProperty]
    public ?string $status;

    /** @var bool Whether the domain is valid or not */
    public bool $isValid = false;

    /**
     * We sometimes do not receive the protocol from the user, so we should not send it the payload as it may cause validation issues
     * When this is true, we send the url without protocol, the rest is handled on Argus side.
     * @var bool
     */
    public bool $validateWithoutProtocol = false;

    /** @var bool Whether the domain requires www subdomain to load or not */
    public bool $needsWww = false;

    /** @var bool Whether the domain is working only explicitly without www or not */
    public bool $needsNoWww = false;

    /** @var bool Whether the domain requires https connection or not */
    public bool $needsHttps = false;

    /** @var int the http code of the response */
    public int $httpCode;

    /** @var int Content length in bytes of the response */
    public int $contentLength;

    /** @var int The amount of words in the content */
    public int $wordsCount;

    /** @var int The amount of redirects performed until the final page is reached */
    public int $redirectCount;

    /** @var int The amount of retries */
    public int $retryCount;

    /** @var string|null The initial URL of the request */
    public ?string $initialUrl;

    /** @var string|null The url finally redirected to */
    public ?string $redirectUrl;

    /** @var string|null The url retried */
    public ?string $retryUrl = null;

    /** @var string|null The url filtered */
    public ?string $filteredUrl = null;

    /** @var int Default timeout to wait until giving up */
    public int $timeout = 30;

    /** @var string Country shortcode for IPs to be used for validation */
    public string $countryShortCode;

    /** @var string The type of validation performed, full means that the content is loaded and status code is analyzed, host means, that only the validity of the host is verified */
    #[Choice(choices: [self::VALIDATION_MODE_FULL, self::VALIDATION_MODE_HOST])]
    public string $validationMode = self::VALIDATION_MODE_FULL;

    /**
     * sets domain validation mode
     * @param string $validationMode
     * @return void
     */
    public function setDomainValidationMode(string $validationMode): void
    {
        $this->validationMode = $validationMode;
    }

    /**
     * @return string
     */
    public function uniqueKey(): string
    {
        return get_class($this) . '_' . $this->validationMode . '_' . $this->getValidatedDomainURL();
    }

    /**
     * @return string
     */
    public function getValidatedDomainURL(): string
    {
        $this->setValidateWithoutProtocol($this->parent?->unknownProtocol ?? true);
        return $this->validateWithoutProtocol ? $this->parent->getNameWithWwwAndPath() : $this->parent->getURLFullWithPath();
    }

    /**
     * We sometimes do not receive the protocol from the client, so we should not send it the payload as it may cause validation issues.
     * When this is true, we send the url without the protocol, the rest is handled on Argus' side.
     * @param bool $unknownProtocol
     */
    public function setValidateWithoutProtocol(bool $unknownProtocol = true): void
    {
        $this->validateWithoutProtocol = $unknownProtocol;
    }

    /**
     * If redirect target comes back but is exactly the same, we consider that the domain has no redirect
     * @return void
     */
    public function setProperRedirectTarget(): void
    {
        if (
            (isset($this->status) && $this->status === 'valid') &&
            !empty($this->initialUrl) &&
            !empty($this->redirectUrl) &&
            $this->initialUrl === $this->redirectUrl
        ) {
            $this->redirectCount = 0;
            $this->redirectUrl = null;
        }
    }
}
