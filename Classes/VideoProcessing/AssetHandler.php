<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\VideoProcessing;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use JsonException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\Video;
use Psr\Log\LoggerInterface;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;
use PunktDe\Cloudflare\Stream\Exception\ConfigurationException;
use PunktDe\Cloudflare\Stream\Exception\TransferException;

/**
 * @Flow\Scope("singleton")
 */
class AssetHandler
{

    /**
     * @Flow\Inject
     * @var CloudflareClient
     */
    protected $cloudflareClient;

    /**
     * @Flow\Inject
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @Flow\Inject
     * @var VideoMetaDataRepository
     */
    protected $videoMetaDataRepository;

    /**
     * @param AssetInterface $asset
     * @return void
     * @throws ConfigurationException
     * @throws IllegalObjectTypeException
     * @throws JsonException
     * @throws TransferException
     */
    public function assetCreated(AssetInterface $asset): void
    {
        if (!$this->shouldProcess($asset)) {
            return;
        }

        /** @var Video $asset */
        $videoMetaData = VideoMetaData::fromCloudflareResponse($this->cloudflareClient->uploadVideo($asset));
        $videoMetaData->setVideo($asset);
        $this->videoMetaDataRepository->add($videoMetaData);
    }

    /**
     * @param AssetInterface $asset
     * @return void
     * @throws JsonException
     * @throws IllegalObjectTypeException
     * @throws ConfigurationException
     * @throws TransferException
     */
    public function assetRemoved(AssetInterface $asset): void
    {
        if (!$this->shouldProcess($asset)) {
            return;
        }

        /** @var Video $asset */
        $videoMetaData = $this->videoMetaDataRepository->findOneByVideo($asset);

        if (!$videoMetaData instanceof VideoMetaData) {
            return;
        }

        $response = $this->cloudflareClient->deleteVideo($videoMetaData->getCloudflareUid());
        if (!$response->isSuccess()) {
            $this->logger->warning(sprintf('Video %s (Cloudflare UID: %s) could not be deleted from cloudflare: %s', $asset->getTitle(), $videoMetaData->getCloudflareUid(), $response->getErrorInformation()), LogEnvironment::fromMethodName(__METHOD__));
        }

        $this->videoMetaDataRepository->remove($videoMetaData);
    }

    /**
     * @param AssetInterface $asset
     * @return void
     */
    public function assetUpdated(AssetInterface $asset): void
    {
    }

    /**
     * @param AssetInterface $asset
     * @return bool
     */
    private function shouldProcess(AssetInterface $asset): bool
    {
        if (!$asset instanceof Video) {
            return false;
        }

        if (!$this->cloudflareClient->isReady()) {
            $this->logger->warning('Cloudflare video streaming is installed, but no credentials are configured. Processing is skipped', LogEnvironment::fromMethodName(__METHOD__));
            return false;
        }

        return true;
    }
}
