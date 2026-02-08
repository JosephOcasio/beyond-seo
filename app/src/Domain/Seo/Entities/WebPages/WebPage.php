<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages;

use App\Domain\Seo\Entities\Domains\Domain;
use App\Domain\Seo\Entities\Domains\DomainValidation;
use DDD\Domain\Base\Entities\Entity;
use DDD\Infrastructure\Exceptions\MethodNotAllowedException;
use DDD\Infrastructure\Libs\Datafilter;
use DDD\Infrastructure\Libs\IdnaConvert;
use Exception;

//use App\Domain\Seo\Repo\Argus\WebPages\ArgusWebPage;

/**
 * @property WebPages $parent
 * @method WebPages getParent()
 */
//#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusWebPage::class)]
class WebPage extends Entity
{
    /** @var Domain|null The Domain associated to this WebPage */
    public ?Domain $domain = null;

    /** @var string|null The full URL of the WebPage */
    public ?string $url;

    /** @var string|null The relative path of the WebPage on its Domain */
    public ?string $path;

    /** @var bool Whether the domain requires https */
    public bool $needsHttps = false;

    /** @var DomainValidation|null */
    public ?DomainValidation $valid;

    /** @var WebPageContent Loaded from website */
    public WebPageContent $content;

    public function __construct(?string $path = null, ?Domain &$domain = null)
    {
        if ($domain) {
            $this->domain = $domain;
        }
        $this->setPath($path);
        parent::__construct();
    }

    public function setPath(?string $path): void
    {
        if (!$path) {
            return;
        }
        if (!$this->domain) {
            try {
                $domainName = IdnaConvert::convertToUTF8($path);
                $domainName = Datafilter::domain($path, false, false, false);
            } catch (Exception $e) {
                throw new MethodNotAllowedException(
                    'A WebPage needs either a valid domain or a fully qualified URL as path'
                );
            }
            $this->domain = new Domain($domainName);
        }
        if (str_contains($path, $this->domain->name)) {
            $path = substr($path, strpos($path, $this->domain->name) + strlen($this->domain->name));
        }
        if ($path && $path[strlen($path) - 1] === '?') // handle issues like domain.de/?
        {
            $path = substr($path, 0, -1);
        }
        if ($path && $path[strlen($path) - 1] === '/') // handle issues like domain.de/?
        {
            $path = substr($path, 0, -1);
        }
        if ($path === '/') {
            $path = '';
        }
        if (strlen($path) > 1 && $path[0] !== '/') {
            $path = '/' . $path;
        }
        $this->path = $path;
        $this->url = $this->getURLFull();
    }

    public function getURLFull(): string
    {
        return ($this->domain ? (($this->needsHttps ? 'https://' : 'http://') . $this->domain->getNameWithWww(
                )) : '') . (isset($this->path) ? '/' : '') . (isset($this->path) ? $this->path : '');
    }

    public function uniqueKey(): string
    {
        return self::uniqueKeyStatic($this->getURLFull());
    }
}
