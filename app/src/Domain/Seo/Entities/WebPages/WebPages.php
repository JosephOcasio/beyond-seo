<?php

declare(strict_types=1);

namespace App\Domain\Seo\Entities\WebPages;

use App\Domain\Seo\Entities\Domains\Domain;
use App\Domain\Seo\Repo\Argus\WebPages\ArgusWebPages;
use DDD\Domain\Base\Entities\EntitySet;
use DDD\Domain\Base\Entities\LazyLoad\LazyLoadRepo;

/**
 * @property WebPage[] $elements;
 * @method WebPage getByUniqueKey(string $uniqueKey)
 * @method WebPage[] getElements()
 * @method WebPage first()
 * @property Domain $parent
 * @method Domain getParent()
 */
#[LazyLoadRepo(LazyLoadRepo::ARGUS, ArgusWebPages::class)]
class WebPages extends EntitySet
{
    /**
     * Returns whether there is a page associated with the given domain.
     *
     * @param Domain $domain The domain to check for a page.
     * @return bool True if a page is found for the given domain, false otherwise.
     */
    public function hasPageForDomain(Domain $domain):bool {
        foreach ($this->getElements() as $webPage){
            if (isset($webPage->domain) && $webPage->domain->getNameWithoutWww() == $domain->getNameWithoutWww())
                return true;
        }
        return false;
    }
}
