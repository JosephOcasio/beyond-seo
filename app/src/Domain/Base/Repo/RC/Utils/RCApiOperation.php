<?php

declare (strict_types=1);

namespace App\Domain\Base\Repo\RC\Utils;

use App\Domain\Base\Repo\RC\Enums\RCApiOperationType;
use App\Domain\Base\Repo\RC\RCEntity;
use DDD\Domain\Base\Entities\Entity;
use DDD\Domain\Base\Entities\ValueObject;
use DDD\Infrastructure\Traits\Serializer\SerializerTrait;

/**
 * Encapsulate an RC API Operation Component, including function, params etc.
 *
 */
class RCApiOperation
{
    use SerializerTrait;

    public RCEntity|Entity|ValueObject|null $entity;
    public ?string $id;
    public ?string $endpoint;
    public ?array $params;
    public ?array $generalParams;
    public array|object|null $results;
    public int $mergelimit = 1;

    public function __construct(
        RCEntity|Entity|ValueObject &$entity,
        string                      $id,
        string                      $endpoint,
        array                       &$payload,
    ) {
        $this->entity = $entity;
        $this->id = $id;
        if (isset($payload['path'])) {
            $endpoint = self::getEndpointWithPathParametersApplied($endpoint, $payload);
            unset($payload['path']);
        }

        $this->endpoint = $endpoint;
        if (isset($payload['merge'])) {
            $this->params = $payload['params'];
            if (isset($payload['general_params'])) {
                $this->generalParams = $payload['general_params'];
            }

            $this->mergelimit = $payload['mergelimit'];
        } else {
            $this->params = $payload;
        }
    }

    /**
     * Returns endpoint with path parameters applied, e.g.
     * POST:/rc-business-listings/listings/{directoryName} and path patameters  ['path' => ['directoryName' => 'google']]
     * results in POST:/rc-business-listings/listings/google
     * @param string $endpoint
     * @param array $payload
     * @return array|string|string[]
     */
    public static function getEndpointWithPathParametersApplied(string $endpoint, array &$payload)
    {
        if (!isset($payload['path'])) {
            return $endpoint;
        }
        foreach ($payload['path'] as $pathParamterName => $pathParamterValue) {
            $endpoint = str_replace("{{$pathParamterName}}", $pathParamterValue, $endpoint);
        }
        return $endpoint;
    }

    /**
     * Handles the response of the call by calling the corresponding Method on the initiating RCEntity
     * @param mixed $results
     * @param string $operationType
     * @return void
     */
    public function handleResponse(mixed $results, string $operationType = RCApiOperationType::LOAD): void
    {
        $this->results = $results;
        if ($operationType === RCApiOperationType::LOAD) {
            $this->entity->handleLoadResponse($results, $this);
            return;
        }
        if ($operationType === RCApiOperationType::UPDATE) {
            $this->entity->handleUpdateResponse($results, $this);
            return;
        }
        if ($operationType === RCApiOperationType::DELETE) {
            $this->entity->handleDeleteResponse($results, $this);
            return;
        }
        if ($operationType === RCApiOperationType::CREATE) {
            $this->entity->handleCreateResponse($results, $this);
            return;
        }
        if ($operationType === RCApiOperationType::SYNCHRONIZE) {
            $this->entity->handleSynchronizeResponse($results, $this);
        }
    }

    /**
     * @return string
     */
    public function uniqueKey(): string
    {
        return $this->id;
    }
}
