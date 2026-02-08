<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\Domains;

use App\Domain\Seo\Entities\Domains\DomainContent\CompanyObjectiveSummary;
use App\Domain\Seo\Repo\Argus\Domains\ArgusDomainContent;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoad;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptions;
use DDD\Domain\Base\Entities\QueryOptions\QueryOptionsTrait;
use DDD\Domain\Base\Entities\ValueObject;

/**
 * @method Domain getParent()
 * @property Domain $parent
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusDomainContent::class)]
#[QueryOptions(top: 10)]
class DomainContent extends ValueObject
{
    use QueryOptionsTrait;

    /** @var string|null Content extracted from multiple websites */
    public ?string $content;

    /** @var CompanyObjectiveSummary AI Summarized company summary based on website content, 100-150 words long containing the profile of the companies activites, offerings, products and services and USPs. */
    #[LazyLoad(LazyLoadRepo::ARGUS)]
    public ?CompanyObjectiveSummary $objectiveSummary;

    public function uniqueKey(): string
    {
        $key = '';
        if ($this->getParent()) {
            $key = $this->getParent()->uniqueKey();
        }
        return self::uniqueKeyStatic($key);
    }
}
