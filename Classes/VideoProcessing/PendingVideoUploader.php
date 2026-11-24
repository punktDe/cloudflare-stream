<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\VideoProcessing;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Log\Utility\LogEnvironment;
use Neos\Flow\Persistence\Doctrine\PersistenceManager;
use Neos\Media\Domain\Service\AssetService;
use Psr\Log\LoggerInterface;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;

/**
 * Uploads all videos to cloudflare, which are not yet uploaded. Used if processing.async is enabled.
 */
#[Flow\Scope("singleton")]
class PendingVideoUploader
{
    #[Flow\Inject]
    protected CloudflareClient $cloudflareClient;

    #[Flow\Inject]
    protected VideoMetaDataRepository $videoMetaDataRepository;

    #[Flow\Inject]
    protected PersistenceManager $persistenceManager;

    #[Flow\Inject]
    protected AssetService $assetService;

    #[Flow\Inject]
    protected LoggerInterface $logger;

    #[Flow\InjectConfiguration(path: "processing.maximumAttempts", package: "PunktDe.Cloudflare.Stream")]
    protected int $maximumAttempts = 3;

    /**
     * Counts failed upload attempts per video, injected via Objects.yaml
     *
     * @var VariableFrontend
     */
    protected $uploadAttemptsCache;

    /**
     * @param callable|null $onVideoProcessed Called with the filename and the result after each video
     * @return int Number of uploaded videos
     */
    public function uploadPendingVideos(?callable $onVideoProcessed = null): int
    {
        if (!$this->cloudflareClient->isReady()) {
            $this->logger->debug('Cloudflare video streaming is installed, but no credentials are configured. Pending uploads are skipped', LogEnvironment::fromMethodName(__METHOD__));
            return 0;
        }

        $uploadedVideos = 0;

        foreach ($this->videoMetaDataRepository->findVideosWithoutMetaData() as $video) {
            $videoIdentifier = $this->persistenceManager->getIdentifierByObject($video);
            $filename = $video->getResource()->getFilename();
            $attempts = (int)$this->uploadAttemptsCache->get($videoIdentifier);

            if ($attempts >= $this->maximumAttempts) {
                $this->callIfSet($onVideoProcessed, $filename, sprintf('Skipped after %s failed attempts', $attempts));
                continue;
            }

            try {
                $videoMetaData = VideoMetaData::fromCloudflareResponse($video, $this->cloudflareClient->uploadVideo($video));
            } catch (\Exception $exception) {
                $attempts++;
                $this->uploadAttemptsCache->set($videoIdentifier, $attempts);
                $this->logger->error(sprintf('Upload of video %s (%s) to cloudflare failed, attempt %s of %s: %s', $filename, $videoIdentifier, $attempts, $this->maximumAttempts, $exception->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
                $this->callIfSet($onVideoProcessed, $filename, sprintf('Failed (attempt %s of %s)', $attempts, $this->maximumAttempts));
                continue;
            }

            // The upload can take several minutes, meanwhile the video might have been deleted in Neos
            if (!$this->videoMetaDataRepository->videoExists($videoIdentifier)) {
                $this->deleteFromCloudflare($videoMetaData->getCloudflareUid(), $filename);
                $this->uploadAttemptsCache->remove($videoIdentifier);
                $this->logger->info(sprintf('Video %s (%s) was deleted during the upload, removed it from cloudflare again', $filename, $videoIdentifier), LogEnvironment::fromMethodName(__METHOD__));
                $this->callIfSet($onVideoProcessed, $filename, 'Deleted during upload, removed from cloudflare');
                continue;
            }

            $this->videoMetaDataRepository->add($videoMetaData);
            try {
                $this->persistenceManager->persistAll();
            } catch (\Exception $exception) {
                // Most likely the video was deleted right after the check above. As the entity manager
                // is closed after a failed flush, the remaining videos are processed by the next run.
                $this->deleteFromCloudflare($videoMetaData->getCloudflareUid(), $filename);
                $this->logger->error(sprintf('Could not store cloudflare meta data of video %s (%s), removed it from cloudflare again: %s', $filename, $videoIdentifier, $exception->getMessage()), LogEnvironment::fromMethodName(__METHOD__));
                $this->callIfSet($onVideoProcessed, $filename, 'Could not store meta data, removed from cloudflare');
                break;
            }

            // Flushes the content cache of pages which were rendered without the cloudflare meta data
            $this->assetService->emitAssetUpdated($video);
            $this->uploadAttemptsCache->remove($videoIdentifier);
            $uploadedVideos++;
            $this->callIfSet($onVideoProcessed, $filename, 'Uploaded');
        }

        return $uploadedVideos;
    }

    private function deleteFromCloudflare(string $cloudflareUid, string $filename): void
    {
        try {
            $response = $this->cloudflareClient->deleteVideo($cloudflareUid);
            if ($response->isSuccess()) {
                return;
            }
            $error = $response->getErrorInformation();
        } catch (\Exception $exception) {
            $error = $exception->getMessage();
        }

        $this->logger->error(sprintf('Video %s (Cloudflare UID: %s) could not be deleted from cloudflare, please remove it manually: %s', $filename, $cloudflareUid, $error), LogEnvironment::fromMethodName(__METHOD__));
    }

    private function callIfSet(?callable $callback, string $filename, string $result): void
    {
        if ($callback !== null) {
            $callback($filename, $result);
        }
    }
}
