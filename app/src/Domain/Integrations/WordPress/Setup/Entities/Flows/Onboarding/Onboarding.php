<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Flows\Onboarding;

use App\Domain\Common\Entities\Keywords\Keywords;
use App\Domain\Integrations\WordPress\Setup\Entities\Flows\Requirements\WPRequirements;
use App\Domain\Integrations\WordPress\Setup\Entities\WPSetupSetting;
use DDD\Domain\Base\Entities\ValueObject;

/**
 * Class Onboarding
 */
class Onboarding extends ValueObject {

    /** @var WPRequirements|null requirements */
    public ?WPRequirements $requirements = null;

    /** @var Keywords|null keywords */
    public ?Keywords $keywords = null;

    /** @var int|null maxAllowedKeywords */
    public ?int $maxAllowedKeywords = null;

    /** @var WPSetupSetting|null setupSettings */
    public ?WPSetupSetting $setupSettings = null;
}
