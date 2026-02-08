<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\Domains;

use App\Domain\Seo\Entities\WebPages\WebPages;
use App\Domain\Seo\Entities\Website;
use App\Domain\Seo\Repo\Argus\Domains\ArgusDomain;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Infrastructure\Libs\Datafilter;
use DDD\Infrastructure\Libs\IdnaConvert;
use DDD\Infrastructure\Traits\Serializer\Attributes\HideProperty;
use Exception;

/**
 * @method Website getParent()
 * @property Website $parent
 */
class Domain extends Entity
{
    /** @var string|null The name of the domain */
    public ?string $name;

    /** @var string|null The path of the domain */
    public ?string $path;

    /** @var bool Whether the domain requires www */
    public bool $needsWww = false;

    /** @var bool Whether the domain requires https */
    public bool $needsHttps = false;

    /** @var bool Whether to exclude path in page URL, e.g. in Landingpages we don't need the path too because that's contained in page url. */
    public bool $excludePathInPageUrl = false;

    /**
     * @var bool
     * Used for validation purposes. There are cases where the protocol is unknown, e.g. in the case the client doesn't provide it
     * This flag overrides the needsHttps flag when handling the validation
     */
    #[HideProperty]
    public bool $unknownProtocol = true;

    /** @var DomainValidation|null */
    #[LazyLoad(LazyLoadRepo::ARGUS)]
    public ?DomainValidation $valid;

    /** @var DomainContent Loaded from website */
    #[LazyLoad(LazyLoadRepo::ARGUS)]
    public DomainContent $content;

    /** @var WebPages Web Pages located on the Domain */
    public WebPages $webPages;

    /**
     * @param string|null $name
     * @param string|null $path
     * @param bool|null $secure
     */
    public function __construct(string $name = null, ?string $path = '', ?bool $secure = null)
    {
        if (!$name) {
            parent::__construct();
            return;
        }

        $this->setName($name);
        if ($path) {
            $this->setPath($path);
        }
        $this->setProtocol($name, $secure);

        parent::__construct();
    }

    /**
     * @param string $name
     * @return void
     */
    public function setName(string $name): void
    {
        try {
            $name = IdnaConvert::convertToUTF8($name);
            $name = Datafilter::domain($name, false, false, false);
        } catch (Exception $e) {
        }
        $this->needsWww = str_contains(substr($name, 0, 4), 'www.');
        $this->name = $this->needsWww ? substr($name, 4) : $name;
    }

    /**
     * @param string $path
     * @return void
     */
    public function setPath(string $path): void
    {
        try {
            $path = IdnaConvert::convertToUTF8($path);
            if (!is_string($path)) {
                $path = '';
            }
        } catch (Exception) {
        }
        if (str_contains($path, $this->name)) {
            $path = substr($path, strpos($path, $this->name) + strlen($this->name));
        }
        // handle issues like domain.de/?
        if ($path && $path[-1] === '?') {
            $path = substr($path, 0, -1);
            if ($path && $path[-1] === '/') {
                $path = substr($path, 0, -1);
            }
        }
        if ($path === '/') {
            $path = '';
        }
        if (strlen($path) > 1 && $path[0] != '/') {
            $path = '/' . $path;
        }
        if (empty($path)) {
            $path = '';
        }
        $this->path = $path;
    }

    /**
     * @param string $url
     * @param bool|null $hasSecureProtocol
     * @return void
     */
    public function setProtocol(string $url, ?bool $hasSecureProtocol = null): void
    {
        if (is_bool($hasSecureProtocol)) {
            $this->needsHttps = $hasSecureProtocol;
            $this->unknownProtocol = false;
            return;
        }

        $protocol = Datafilter::getProtocolFromUrl($url);
        $this->needsHttps = $protocol === 'https';
        $this->unknownProtocol = !in_array($protocol, ['http', 'https']);
    }

    /**
     * cleans and normalizes the domain name the same way as it is done in constructor
     * @param $name
     * @return string
     */
    public static function cleanDomain($name): string
    {
        return Datafilter::domain($name, false, false, false);
    }

    /**
     * @return bool
     */
    public function hasPath(): bool
    {
        if (empty($this->path)) {
            return false;
        }
        if ($this->path === '/') {
            return false;
        }
        return true;
    }

    /**
     * @param bool $checkForExclusion
     * @return string
     */
    public function getNameWithPath(bool $checkForExclusion = false): string
    {
        if ($checkForExclusion && $this->excludePathInPageUrl) {
            return $this->name;
        }
        return $this->name . $this->path;
    }

    /**
     * returns full URL including path, e.g. https://www.asd.com/path/
     * @return string
     */
    public function getURLFullWithPath(): string
    {
        return $this->getURLFull() . ($this->path ?? '');
    }

    /**
     * @return string
     */
    public function getURLFull(): string
    {
        return ($this->needsHttps ? 'https://' : 'http://') . $this->getNameWithWww();
    }

    /**
     * @return string
     */
    public function getNameWithoutWww(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getNameWithWww(): string
    {
        return ($this->needsWww ? 'www.' : '') . urlencode($this->name);
    }

    /**
     * @return string
     */
    public function getNameWithWwwAndPath(): string
    {
        $path = $this->path ?? null;
        return $this->getNameWithWww() . ($path ? '/' . $path : '');
    }

    /**
     * @return string
     */
    public function getNameWithoutWwwWithPath(): string
    {
        return $this->name . ($this->path ?? '');
    }

    /**
     * @return string
     */
    public function uniqueKey(): string
    {
        return self::uniqueKeyStatic($this->getNameWithoutWwwWithPath());
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return Datafilter::alias($this->getNameWithoutWwwWithPath());
    }
}
