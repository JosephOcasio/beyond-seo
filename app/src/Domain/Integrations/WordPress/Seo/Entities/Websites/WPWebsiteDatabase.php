<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Seo\Entities\Websites;

use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class WPWebsiteDatabase
 * @property string[] $tables The database tables
 */
class WPWebsiteDatabase extends ValueObject
{
    /** @var string[] $tables The database host */
    public array $tables = [];

    public ?string $size = null;

    // Constructor
    public function __construct()
    {
        parent::__construct();
    }
}