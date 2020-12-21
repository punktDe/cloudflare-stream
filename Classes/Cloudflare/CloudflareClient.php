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
use \TusPhp\Tus\Client as TusClient;

/**
 * @Flow\Scope("singleton")
 */
class CloudflareClient
{

    private const CLOUDFLARE_API_ENDPOINT = 'https://api.cloudflare.com/client/v4/';
    private const CLOUDFLARE_RECOMMENDED_CHUNK_SIZE = 52428800;
    private const CLOUDFLARE_MINIMAL_CHUNK_SIZE = 5242880;

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

        $tusClient = new TusClient((string)$this->buildUriForIdentifier(''), $this->buildGuzzleOptions());
        $tusClient->setApiPath('');
        $key = uniqid('cloudflare-upload', true);

        $tusClient->setKey($key)->file($localVideoFilePath, $video->getResource()->getFilename());
        $tusClient->addMetadata('name', $video->getResource()->getFilename());

        $uploadViaTus = function (int $chunkSize = -1) use ($tusClient, $video): int {
            $timeStarted = microtime(true);
            $bytesUploaded = $tusClient->upload($chunkSize);
            $timeElapsed = microtime(true) - $timeStarted;
            $uploadRate = round(($bytesUploaded / 1024 / 1024) / $timeElapsed, 2);
            $this->logger->info(sprintf('Uploaded video chunk %s / %s of %s to cloudflare. (Time: %s, %s Mb per second)', $bytesUploaded, $tusClient->getFileSize(), $video->getResource()->getFilename(), $timeElapsed, $uploadRate), LogEnvironment::fromMethodName(__METHOD__));

            return $bytesUploaded;
        };

        if ($tusClient->getFileSize() <= self::CLOUDFLARE_RECOMMENDED_CHUNK_SIZE) {
            $uploadViaTus();
        } else {
            do {
                $bytesUploaded = $uploadViaTus(self::CLOUDFLARE_RECOMMENDED_CHUNK_SIZE);
            } while ($bytesUploaded < $tusClient->getFileSize() || $bytesUploaded === 0);
        }

        $response = $tusClient->getClient()->head($tusClient->getUrl());

        $cloudflareResponse = new CloudflareResponse(
            $response->getStatusCode() === 200,
            [],
            [],
            [
                'uid' => current($response->getHeader('stream-media-id')),
            ],
            $response->getStatusCode()
        );

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

        $options = $this->buildGuzzleOptions();
        $options['headers']['Content-Type'] = 'application/json';

        if ($this->client === null) {
            $this->client = new Client($options);
        }

        return $this->client;
    }

    private function buildGuzzleOptions(): array
    {
        return [
            'proxy' => $this->transfer['proxyUrl'] ?? '',
            'timeout' => 300,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->authentication['token'],
            ],
        ];
    }
}
