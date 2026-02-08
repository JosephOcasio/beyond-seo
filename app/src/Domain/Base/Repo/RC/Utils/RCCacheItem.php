<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC\Utils;

use DDD\Infrastructure\Traits\Serializer\SerializerTrait;

/**
 * @todo replace Api_Cache Class by symfony cache and config
 * getMulti is essential here, it loads multiple cache elements with a single redis query and
 * by thid reduces roundtrip times significantly
 */
class RCCacheItem
{
    use SerializerTrait;

    public bool $loaded = false;
    public ?int $validUntil;
    public mixed $data;
    public ?int $cacheSource = RCCache::CACHELEVEL_MEMORY;
}