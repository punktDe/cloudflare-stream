<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Tests\Functional\VideoProcessing;

/*
 *  (c) 2026 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Cache\CacheManager;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Media\Domain\Model\Video;
use Neos\Media\Domain\Service\AssetService;
use Neos\Media\Domain\Strategy\AssetModelMappingStrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareResponse;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;
use PunktDe\Cloudflare\Stream\Exception\TransferException;
use PunktDe\Cloudflare\Stream\VideoProcessing\AssetHandler;
use PunktDe\Cloudflare\Stream\VideoProcessing\PendingVideoUploader;

/**
 * Uses a mocked cloudflare client, so no cloudflare credentials are needed
 */
class PendingVideoUploaderTest extends FunctionalTestCase
{
    protected static $testablePersistenceEnabled = true;

    private const CLOUDFLARE_UID = 'test-cloudflare-uid';

    private PendingVideoUploader $pendingVideoUploader;

    private AssetHandler $assetHandler;

    private AssetService $assetService;

    private VideoMetaDataRepository $videoMetaDataRepository;

    /**
     * @var CloudflareClient&MockObject
     */
    private CloudflareClient $cloudflareClientMock;

    private CloudflareClient $originalCloudflareClient;

    public function setUp(): void
    {
        parent::setUp();
        $this->pendingVideoUploader = $this->objectManager->get(PendingVideoUploader::class);
        $this->assetHandler = $this->objectManager->get(AssetHandler::class);
        $this->assetService = $this->objectManager->get(AssetService::class);
        $this->videoMetaDataRepository = $this->objectManager->get(VideoMetaDataRepository::class);
        $this->originalCloudflareClient = $this->objectManager->get(CloudflareClient::class);

        $this->cloudflareClientMock = $this->createMock(CloudflareClient::class);
        $this->cloudflareClientMock->method('isReady')->willReturn(true);

        $this->inject($this->assetHandler, 'cloudflareClient', $this->cloudflareClientMock);
        $this->inject($this->assetHandler, 'asyncProcessing', true);
        $this->inject($this->pendingVideoUploader, 'cloudflareClient', $this->cloudflareClientMock);
        $this->inject($this->pendingVideoUploader, 'maximumAttempts', 2);

        $this->objectManager->get(CacheManager::class)->getCache('PunktDe_Cloudflare_Stream_UploadAttempts')->flush();
    }

    public function tearDown(): void
    {
        // Both are singletons and would keep the mock for other tests
        $this->inject($this->assetHandler, 'cloudflareClient', $this->originalCloudflareClient);
        $this->inject($this->assetHandler, 'asyncProcessing', false);
        $this->inject($this->pendingVideoUploader, 'cloudflareClient', $this->originalCloudflareClient);
        $this->inject($this->pendingVideoUploader, 'maximumAttempts', 3);
        parent::tearDown();
    }

    public function testCreatingAVideoDoesNotUploadItIfAsyncProcessingIsEnabled(): void
    {
        $this->cloudflareClientMock->expects(self::never())->method('uploadVideo');

        $video = $this->createVideo();

        self::assertNull($this->videoMetaDataRepository->findOneByVideo($video));
    }

    public function testPendingVideoIsUploadedAndMetaDataIsStored(): void
    {
        $video = $this->createVideo();
        $this->cloudflareClientMock->expects(self::once())->method('uploadVideo')->willReturn($this->successfulResponse());

        self::assertSame(1, $this->pendingVideoUploader->uploadPendingVideos());

        $this->persistenceManager->clearState();
        $videoMetaData = $this->videoMetaDataRepository->findOneByVideo($video);
        self::assertNotNull($videoMetaData);
        self::assertSame(self::CLOUDFLARE_UID, $videoMetaData->getCloudflareUid());
    }

    public function testAlreadyUploadedVideoIsNotUploadedAgain(): void
    {
        $this->createVideo();
        $this->cloudflareClientMock->expects(self::once())->method('uploadVideo')->willReturn($this->successfulResponse());

        $this->pendingVideoUploader->uploadPendingVideos();
        self::assertSame(0, $this->pendingVideoUploader->uploadPendingVideos());
    }

    public function testFailedUploadIsRetriedUntilMaximumAttemptsAreReached(): void
    {
        $video = $this->createVideo();
        $this->cloudflareClientMock->expects(self::exactly(2))->method('uploadVideo')->willThrowException(new TransferException('Upload failed', 1763560000));

        $this->pendingVideoUploader->uploadPendingVideos();
        $this->pendingVideoUploader->uploadPendingVideos();
        $this->pendingVideoUploader->uploadPendingVideos();

        self::assertNull($this->videoMetaDataRepository->findOneByVideo($video));
    }

    public function testVideoDeletedDuringUploadIsRemovedFromCloudflare(): void
    {
        $video = $this->createVideo();
        $this->cloudflareClientMock->expects(self::once())->method('uploadVideo')->willReturnCallback(function (Video $videoToUpload) {
            // Simulates an editor deleting the video while it is uploaded
            $this->assetService->getRepository($videoToUpload)->remove($videoToUpload);
            $this->persistenceManager->persistAll();
            return $this->successfulResponse();
        });
        $this->cloudflareClientMock->expects(self::once())->method('deleteVideo')->with(self::CLOUDFLARE_UID)->willReturn(new CloudflareResponse(true, [], [], []));

        self::assertSame(0, $this->pendingVideoUploader->uploadPendingVideos());

        $this->persistenceManager->clearState();
        self::assertCount(0, $this->videoMetaDataRepository->findAll());
    }

    private function createVideo(): Video
    {
        $resource = $this->objectManager->get(ResourceManager::class)->importResource(realpath(__DIR__ . '/../Fixture/Sample.mov'));
        $targetType = $this->objectManager->get(AssetModelMappingStrategyInterface::class)->map($resource);

        /** @var Video $video */
        $video = new $targetType($resource);
        $this->assetService->getRepository($video)->add($video);
        $this->persistenceManager->persistAll();

        return $video;
    }

    private function successfulResponse(): CloudflareResponse
    {
        return new CloudflareResponse(true, [], [], [
            'uid' => self::CLOUDFLARE_UID,
            'playback' => ['hls' => 'https://example.com/video.m3u8', 'dash' => 'https://example.com/video.mpd'],
            'thumbnail' => 'https://example.com/thumbnail.jpg',
        ], 200);
    }
}
