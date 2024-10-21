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
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\Video;
use Psr\Log\LoggerInterface;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;
use PunktDe\Cloudflare\Stream\Exception\ConfigurationException;
use PunktDe\Cloudflare\Stream\Exception\TransferException;

#[Flow\Scope("singleton")]
class AssetHandler
{
    #[Flow\Inject]
    protected CloudflareClient $cloudflareClient;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\Inject]
    protected VideoMetaDataRepository $videoMetaDataRepository;

    #[Flow\Inject]
    protected PersistenceManager $persistenceManager;

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
        /** @var Video $asset */
        $this->uploadIfNecessary($asset);
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
        $this->removeAsset($asset);
    }

    /**
     * @param AssetInterface $asset
     * @throws ConfigurationException
     * @throws IllegalObjectTypeException
     * @throws JsonException
     * @throws TransferException
     */
    public function assetResourceReplaced(AssetInterface $asset): void
    {
        $this->removeAsset($asset);
        $this->uploadIfNecessary($asset);
    }

    /**
     * Happens if label / description is changed. We use it here to check the existence
     * in cloudflare.
     *
     * @param AssetInterface $asset
     * @return void
     * @throws ConfigurationException
     * @throws IllegalObjectTypeException
     * @throws JsonException
     * @throws TransferException
     */
    public function assetUpdated(AssetInterface $asset): void
    {
        /** @var Video $asset */
        $this->uploadIfNecessary($asset);
    }

    /**
     * @param AssetInterface $asset
     * @return bool If the video was uploaded
     * @throws ConfigurationException
     * @throws IllegalObjectTypeException
     * @throws JsonException
     * @throws TransferException
     */
    public function uploadIfNecessary(AssetInterface $asset): bool
    {
        if (!$this->shouldProcess($asset)) {
            return false;
        }

        /** @var Video $asset */
        $videoMetaData = $this->videoMetaDataRepository->findOneByVideo($asset);

        if ($videoMetaData instanceof VideoMetaData) {
            $response = $this->cloudflareClient->getVideo($videoMetaData->getCloudflareUid());
            if ($response->isSuccess()) {
                $videoMetaData->setValuesFromCloudflareResponse($response);
                $this->videoMetaDataRepository->update($videoMetaData);
                return false;
            }

            $this->videoMetaDataRepository->remove($videoMetaData);
        }

        try {
            $videoMetaData = VideoMetaData::fromCloudflareResponse($this->cloudflareClient->uploadVideo($asset));
        } catch (\Exception $e) {
            // Keep the uploaded video and resource
            $this->persistenceManager->persistAll();
            throw $e;
        }

        $videoMetaData->setVideo($asset);
        $this->videoMetaDataRepository->add($videoMetaData);
        return true;
    }

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

    /**
     * @param AssetInterface $asset
     * @throws ConfigurationException
     * @throws IllegalObjectTypeException
     * @throws JsonException
     * @throws TransferException
     */
    private function removeAsset(AssetInterface $asset): void
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
}
