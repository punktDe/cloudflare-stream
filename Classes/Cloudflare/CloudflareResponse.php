<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Cloudflare;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */


use Psr\Http\Message\ResponseInterface;
use PunktDe\Cloudflare\Stream\Exception\TransferException;

class CloudflareResponse
{

    protected bool $success = false;

    protected array $errors = [];

    protected array $messages = [];

    protected array $result = [];

    protected int $httpStatus = 0;

    /**
     * CloudflareResponse constructor.
     * @param bool $success
     * @param array $errors
     * @param array $messages
     * @param array $result
     * @param int $httpStatus
     */
    public function __construct(bool $success, array $errors, array $messages, array $result, int $httpStatus = 0)
    {
        $this->success = $success;
        $this->errors = $errors;
        $this->messages = $messages;
        $this->result = $result;
        $this->httpStatus = $httpStatus;
    }

    /**
     * @return static
     * @throws TransferException
     * @throws \JsonException
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        try {
            $responseData = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            return new static(true, [], [], [], $response->getStatusCode());
        }

        return new static(
            (bool)$responseData['success'],
            $responseData['errors'] ?? [],
            $responseData['messages'] ?? [],
            $responseData['result'] ?? [],
            $response->getStatusCode()
        );
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * @return int
     */
    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * Returns the error information as string for log messages and frontend output
     * @return string
     */
    public function getErrorInformation(): string
    {
        return sprintf('StatusCode: %s. CloudFlare Errors: %s', $this->httpStatus, implode(
            ',',
            array_map(static function ($error) {
                return sprintf('%s [Code: %s]', $error['message'] ?? 'Unknown error', $error['code']);
            }, $this->errors)
        ));
    }
}
