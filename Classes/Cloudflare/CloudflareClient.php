<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Cloudflare;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Uri;
use JsonException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Http\Factories\UriFactory;
use Neos\Media\Domain\Model\Video;
use Neos\Utility\Files;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use PunktDe\Cloudflare\Stream\Exception\ConfigurationException;
use PunktDe\Cloudflare\Stream\Exception\TransferException;

/**
 * @Flow\Scope("singleton")
 */
class CloudflareClient
{

    private const CLOUDFLARE_API_ENDPOINT = 'https://api.cloudflare.com/client/v4/';

    /**
     * @Flow\InjectConfiguration(package="PunktDe.Cloudflare.Stream", path="cloudflare.authentication")
     * @var string[]
     */
    protected array $authentication = [];

    /**
     * @Flow\InjectConfiguration(package="PunktDe.Cloudflare.Stream", path="transfer")
     * @var string[]
     */
    protected array $transfer = [];

    /**
     * @Flow\Inject
     * @var UriFactory
     */
    protected ?UriFactory $uriFactory;

    /**
     * @var Client|null
     */
    protected ?Client $client = null;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @param Video $video
     * @return CloudflareResponse
     * @throws ConfigurationException
     * @throws JsonException
     * @throws TransferException
     */
    public function uploadVideo(Video $video): CloudflareResponse
    {
        $localVideoFilePath = $video->getResource()->createTemporaryLocalCopy();

        $response = $this->getClient()->post(
            $this->buildUriForIdentifier(''),
            [
                'multipart' => [
                    [
                        'Content-type' => 'multipart/form-data',
                        'name' => 'file',
                        'contents' => fopen($localVideoFilePath, 'rb'),
                        'filename' => $video->getResource()->getFilename(),
                    ]
                ]
            ]
        );

        $cloudflareResponse = CloudflareResponse::fromResponse($response);

        if ($cloudflareResponse->isSuccess()) {
            $this->logger->info(sprintf('Successfully uploaded video %s to cloudflare. Cloudflare Id: %s', $video->getResource()->getFilename(), $cloudflareResponse->getResult()['uid']), LogEnvironment::fromMethodName(__METHOD__));
        } else {
            $this->logger->error(sprintf('Error while uploading video %s to cloudflare. Error: %s', $video->getResource()->getFilename(), $cloudflareResponse->getErrorInformation()), LogEnvironment::fromMethodName(__METHOD__));
        }

        return $cloudflareResponse;
    }

    /**
     * @param string $identifier
     * @return CloudflareResponse
     * @throws ConfigurationException
     * @throws JsonException
     * @throws TransferException
     */
    public function deleteVideo(string $identifier): CloudflareResponse
    {
        return CloudflareResponse::fromResponse($this->getClient()->delete($this->buildUriForIdentifier($identifier)));
    }

    /**
     * @return CloudflareResponse
     * @throws ConfigurationException
     * @throws JsonException
     * @throws TransferException
     */
    public function listVideos(): CloudflareResponse
    {
        return CloudflareResponse::fromResponse($this->getClient()->get($this->buildUriForIdentifier('')));
    }

    /**
     * @param string $identifier
     * @return CloudflareResponse
     * @throws ConfigurationException
     * @throws JsonException
     * @throws TransferException
     */
    public function getVideo(string $identifier): CloudflareResponse
    {
        return CloudflareResponse::fromResponse($this->getClient()->get($this->buildUriForIdentifier($identifier)));
    }

    /**
     * @return bool
     */
    public function isReady(): bool
    {
        return !empty($this->authentication['token']) && !empty($this->authentication['accountIdentifier']);
    }

    /**
     * @param string $identifier
     * @return Uri
     */
    private function buildUriForIdentifier(string $identifier): UriInterface
    {
        $uri = $this->uriFactory->createUri(self::CLOUDFLARE_API_ENDPOINT);
        $path = Files::concatenatePaths([$uri->getPath(), 'accounts', $this->authentication['accountIdentifier'], 'stream', $identifier]);
        return $uri->withPath($path);
    }

    /**
     * @return Client
     * @throws ConfigurationException
     */
    private function getClient(): Client
    {
        if (!$this->isReady()) {
            throw new ConfigurationException('No credentials for cloudflare stream were defined.', 1604301275);
        }

        if ($this->client === null) {
            $this->client = new Client([
                'proxy' => $this->transfer['proxyUrl'] ?? '',
                'timeout' => 300,
                'http_errors' => false,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->authentication['token'],
                    'Content-Type' => 'application/json'
                ],
            ]);
        }

        return $this->client;
    }
}
