<?php

namespace App\Domain\Base\Repo\RC\Interfaces;

use App\Domain\Base\Repo\RC\Utils\RCApiOperation;

interface RCLocationUpdateInterface
{
    /**
     * @return void
     */
    public function updateLocation(): void;

    /**
     * @return array
     */
    public function generateLocationUpdatePayload(): array;

    /**
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     */
    public function handleUpdateResponse(
        mixed &$callResponseData,
        RCApiOperation &$apiOperation = null
    ): void;
}
