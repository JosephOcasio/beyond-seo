<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\Domains\DomainContent;

use App\Domain\Seo\Entities\Domains\DomainContent;
use App\Domain\Seo\Repo\Argus\Domains\DomainContent\ArgusCompanyObjectiveSummary;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\ValueObject;

/**
 * @method DomainContent getParent()
 * @property DomainContent $parent
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusCompanyObjectiveSummary::class)]
class CompanyObjectiveSummary extends ValueObject
{

    /** @var string AI Summarized company summary based on website content, 100-150 words long containing the profile of the companies activites, offerings, products and services and USPs. */
    public ?string $objectiveSummaryText;

    public function uniqueKey(): string
    {
        $key = '';
        if ($this->getParent()) {
            $key = $this->getParent()->uniqueKey();
        }
        return self::uniqueKeyStatic($key);
    }
}
