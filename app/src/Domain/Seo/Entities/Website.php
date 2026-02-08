<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities;

use App\Domain\Seo\Entities\CMSTypes\CMSType;
use App\Domain\Seo\Entities\Domains\Domain;
use App\Domain\Seo\Repo\InternalDB\InternalDBWebsite;
use App\Domain\Seo\Services\WebsiteService;
use DDD\Domain\Base\Entities\Attributes\NoRecursiveUpdate;
use DDD\Domain\Base\Entities\DefaultObject;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\Interfaces\IsEmptyInterface;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptionsTrait;

/**
 * Website entity
 */
#[LazyLoadRepo(LazyLoadRepo::LEGACY_DB, InternalDBWebsite::class)]
#[NoRecursiveUpdate]
class Website extends Entity implements IsEmptyInterface
{
    use QueryOptionsTrait;

    /** @var Domain|null Website's domain */
    public ?Domain $domain;

    /** @var CMSType|null Website's current CMSType  */
    public ?CMSType $cmsType;

    /**
     * @param string $cms
     * @return void
     */
    public function setCMSType(string $cms): void
    {
        $websiteService = new WebsiteService();
        $websiteService->setWebsiteCMSType($this, $cms);
    }

    /**
     * @param Website|null $other
     * @return bool
     */
    public function isEqualTo(?DefaultObject $other = null): bool
    {
        if (!$other) {
            return false;
        }
        if ($this?->domain?->getNameWithoutWwwWithPath() != $other?->domain?->getNameWithoutWwwWithPath())
            return false;
        return true;
    }

    /**
     * @param Website|null $other
     * @return bool
     */
    public function isDomainWithoutWwwEqual(?DefaultObject $other = null): bool
    {
        if (!$other) {
            return false;
        }
        if ($this?->domain?->getNameWithoutWww() != $other->domain?->getNameWithoutWww()) {
            return false;
        }
        return true;
    }

    /**
     * @param Website|null $other
     * @return bool
     */
    public function isFullDomainEqual(?DefaultObject $other = null): bool
    {
        if (!$other) {
            return false;
        }
        if ($this?->domain?->getURLFullWithPath() != $other->domain?->getURLFullWithPath()) {
            return false;
        }
        return true;
    }

    /**
     * @return bool
     */
    public function isEmpty(): bool
    {
        return !(($this->domain ?? null) && $this->domain->name);
    }
}
