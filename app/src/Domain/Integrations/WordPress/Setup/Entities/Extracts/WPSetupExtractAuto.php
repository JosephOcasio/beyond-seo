<?php
declare(strict_types=1);

namespace App\Domain\Integrations\WordPress\Setup\Entities\Extracts;


use DDD\Domain\Base\Entities\ValueObject;
use JsonException;

/**
 * Class WPSetupExtractAuto
 */
class WPSetupExtractAuto extends ValueObject
{
    public ?string $content = null;
    public ?string $countryCode = null;
    public ?object $requirements = null;
    public bool $prefillAddressRequirement = false;

    /**
     * WPSetupExtractAuto constructor.
     * @param string|null $content
     * @param string|null $countryCode
     */
    public function __construct(?string $content = null, ?string $countryCode = null)
    {
        $this->content = $content;
        $this->countryCode = $countryCode;
        parent::__construct();
    }

    /**
     * @return object|null
     */
    public function getRequirements(): ?object
    {
        return (object)$this->requirements ?? null;
    }

    /**
     * @return bool
     */
    public function getPrefillAddressRequirement(): bool
    {
        return $this->prefillAddressRequirement;
    }

    /**
     * @param object|null $data
     * @param bool|null $prefilledAddress
     * @throws JsonException
     */
    public function setRequirements(?object $data = null, ?bool $prefilledAddress = false): void
    {
        if ($data) {
            $businessGeoAddress = $data->businessGeoAddress ?? '';
            try {
                $decodedAddress = json_decode($businessGeoAddress, true, 512, JSON_THROW_ON_ERROR);
                $decodedAddress['prefilledAddress'] = $prefilledAddress;
                $businessGeoAddress = json_encode($decodedAddress, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                $businessGeoAddress = '';
            }

            $this->requirements = (object)[
                'businessDescription' => $data->businessDescription ?? '',
                'businessName' => $data->businessName ?? '',
                'businessKeywords' => $data->businessKeywords ?? [],
                'businessCategories' => $data->businessCategories ?? [],
                'businessAddress' => $data->businessAddress ?? '',
                'businessGeoAddress' => $businessGeoAddress,
                'businessServiceArea' => $data->businessServiceArea ?? false,
            ];
        }
    }

    /**
     * @param bool $value
     */
    public function setPrefillAddressRequirement(bool $value = false): void
    {
        $this->prefillAddressRequirement = $value;
    }
}
