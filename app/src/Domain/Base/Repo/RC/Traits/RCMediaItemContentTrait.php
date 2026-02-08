<?php

declare(strict_types=1);

namespace App\Domain\Base\Repo\RC\Traits;

use App\Domain\Base\Repo\RC\Utils\RCApiOperation;
use DDD\Infrastructure\Exceptions\BadRequestException;
use Exception;

trait RCMediaItemContentTrait
{
    use RCTrait;

    /**
     * @throws BadRequestException
     */
    protected function getLoadPayload(): ?array
    {
        $this->validateS3Config();

        if (!$this->getParent()->identifier) {
            throw new BadRequestException(__('No MediaItem name declared!', 'beyond-seo'));
        }

        $params = [
            'region' => $this->getS3Region(),
            'bucket' => $this->getS3Bucket(),
            'file' => $this->getFilePath()
        ];

        $params['compress'] = false;

        return $params;
    }

    protected function getUpdatePayload(): ?array
    {
        $this->validateS3Config();

        $photoExternalPath = $this->getFilePath();

        $params = [
            'region' => $this->getS3Region(),
            'bucket' => $this->getS3Bucket(),
            'file' => $photoExternalPath
        ];

        $params['compress'] = false;
        $params['body'] = $this->base64EncodedContent;

        return $params;
    }

    /**
     * @throws BadRequestException
     */
    protected function getDeletePayload(): ?array
    {
        return $this->getLoadPayload();
    }

    /**
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     * @throws BadRequestException
     */
    public function handleUpdateResponse(mixed &$callResponseData, RCApiOperation &$apiOperation = null): void
    {
        if ($callResponseData->status === 'Bad Request') {
            throw new BadRequestException($callResponseData->message);
        }
        if ($callResponseData->status !== 'OK') {
            throw new Exception($callResponseData->message);
        }
    }

    /**
     * @param mixed $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     * @throws BadRequestException
     */
    public function handleDeleteResponse(mixed &$callResponseData, RCApiOperation &$apiOperation = null): void
    {
        if ($callResponseData->status === 'Bad Request') {
            throw new BadRequestException($callResponseData->message);
        }
        if ($callResponseData->status !== 'OK') {
            throw new Exception($callResponseData->message);
        }
    }

    /**
     * @param mixed|null $callResponseData
     * @param RCApiOperation|null $apiOperation
     * @return void
     * @throws BadRequestException
     * @throws Exception
     */
    public function handleLoadResponse(
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    ): void {

        if (!(($callResponseData->status ?? null) === 'OK')) {
            $this->handleLoadError($callResponseData, $apiOperation);
            return;
        }

        $this->base64EncodedContent = $callResponseData->data;
        $this->populateMediaItemContentInfo();

        $this->postProcessLoadResponse($callResponseData, true);
    }

    private function validateS3Config(): void
    {
        if (!$this->getS3Bucket()) {
            throw new BadRequestException(__('No Bucket declared!', 'beyond-seo'));
        }

        if (!$this->getS3Region()) {
            throw new BadRequestException(__('No Region declared!', 'beyond-seo'));
        }
    }

    abstract function getFilePath();

    abstract function handleLoadError(
        mixed &$callResponseData = null,
        RCApiOperation &$apiOperation = null
    );

    abstract function getS3Bucket();
    abstract function getS3Region();
}
