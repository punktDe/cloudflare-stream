<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Tests\Functional\VideoProcessing;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\ResourceManagement\Exception;
use Neos\Flow\ResourceManagement\ResourceManager;
use Neos\Flow\Tests\FunctionalTestCase;
use Neos\Media\Domain\Model\AssetInterface;
use Neos\Media\Domain\Model\Video;
use Neos\Media\Domain\Service\AssetService;
use Neos\Media\Domain\Strategy\AssetModelMappingStrategyInterface;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;
use PunktDe\Cloudflare\Stream\VideoProcessing\AssetHandler;

class AssetHandlerTest extends FunctionalTestCase
{

    protected static $testablePersistenceEnabled = true;

    /**
     * @var AssetModelMappingStrategyInterface
     */
    protected $assetModelMappingStrategy;

    /**
     * @var ResourceManager
     */
    protected $resourceManager;

    /**
     * @var AssetService
     */
    protected $assetService;

    /**
     * @var AssetHandler
     */
    protected $assetHandler;

    /**
     * @var VideoMetaDataRepository
     */
    protected $videoMetaDataRepository;

    /**
     * @var CloudflareClient
     */
    protected $cloudflareClient;

    public function setUp(): void
    {
        parent::setUp();
        $this->cloudflareClient = $this->objectManager->get(CloudflareClient::class);
        $this->resourceManager = $this->objectManager->get(ResourceManager::class);
        $this->assetModelMappingStrategy = $this->objectManager->get(AssetModelMappingStrategyInterface::class);
        $this->assetService = $this->objectManager->get(AssetService::class);
        $this->assetHandler = $this->objectManager->get(AssetHandler::class);
        $this->videoMetaDataRepository = $this->objectManager->get(VideoMetaDataRepository::class);

        if (!$this->cloudflareClient->isReady()) {
            self::markTestSkipped('Cloudflare credentials are not set, skipping tests');
        }
    }

    /**
     * @test
     */
    public function cloudflareCycle(): void
    {
        /** @var Video $importedVideo */
        $importedVideo = $this->prepareImportedVideo();

        // Upload
        $videoMetaData = $this->videoMetaDataRepository->findOneByVideo($importedVideo);
        self::assertInstanceOf(VideoMetaData::class, $videoMetaData);

        $cloudflareResponse = $this->cloudflareClient->getVideo($videoMetaData->getCloudflareUid());
        self::assertEquals(200, $cloudflareResponse->getHttpStatus());
        self::assertTrue($cloudflareResponse->isSuccess());

        // Remove
        $backupCloudflareUidForProbe = $videoMetaData->getCloudflareUid();
        $videoRepository = $this->assetService->getRepository($importedVideo);
        $videoRepository->remove($importedVideo);
        $this->persistenceManager->persistAll();
        $this->persistenceManager->clearState();

        $videoMetaData = $this->videoMetaDataRepository->findOneByVideo($importedVideo);
        self::assertNull($videoMetaData);
        $cloudflareResponse = $this->cloudflareClient->getVideo($backupCloudflareUidForProbe);
        self::assertEquals(404, $cloudflareResponse->getHttpStatus());
        self::assertFalse($cloudflareResponse->isSuccess());
    }


    /**
     * @return AssetInterface
     * @throws Exception
     */
    private function prepareImportedVideo(): AssetInterface
    {
        $fileName = realpath(__DIR__ . '/../Fixture/Sample.mov');

        $persistentResource = $this->resourceManager->importResource($fileName);
        $targetType = $this->assetModelMappingStrategy->map($persistentResource);

        /** @var Video $video */
        $video = new $targetType($persistentResource);

        $videoRepository = $this->assetService->getRepository($video);
        $videoRepository->add($video);

        $this->persistenceManager->persistAll();
        $assetIdentifier = $this->persistenceManager->getIdentifierByObject($video);

        $this->persistenceManager->clearState();

        return $videoRepository->findByIdentifier($assetIdentifier);
    }
}
