<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Command;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use JsonException;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use Neos\Flow\Persistence\Exception\IllegalObjectTypeException;
use Neos\Flow\Persistence\Exception\InvalidQueryException;
use Neos\Media\Domain\Model\Video;
use Neos\Media\Domain\Repository\VideoRepository;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;
use PunktDe\Cloudflare\Stream\Exception\ConfigurationException;
use PunktDe\Cloudflare\Stream\Exception\TransferException;
use PunktDe\Cloudflare\Stream\VideoProcessing\AssetHandler;

class CloudflareCommandController extends CommandController
{

    /**
     * @Flow\Inject
     * @var CloudflareClient
     */
    protected $cloudflareClient;

    /**
     * @Flow\Inject
     * @var VideoMetaDataRepository
     */
    protected $videoMetaDataRepository;

    /**
     * @Flow\Inject
     * @var VideoRepository
     */
    protected $videoRepository;

    /**
     * @Flow\Inject
     * @var AssetHandler
     */
    protected $assetHandler;

    /**
     * List all uploaded videos for that account
     */
    public function listVideosCommand(): void
    {
        $videoResponse = $this->cloudflareClient->listVideos();

        $this->output->outputTable(
            array_map(static function (array $videoData) {
                return [
                    $videoData['uid'] ?? '',
                    $videoData['meta']['name'] ?? '[Unknown Name]',
                    $videoData['readyToStream'] === true ? '<success>true</success>' : '<error>false</error>',
                    round($videoData['size'] / 1024 / 1024, 2) ?? 0.0,
                    $videoData['duration'] ?? false,
                    ($videoData['input']['width'] ?? 0) . 'x' . ($videoData['input']['height'] ?? 0),
                ];
            }, $videoResponse->getResult()),
            ['UID', 'Name', 'ReadyToStream', 'Size (Mb)', 'Duration (s)', 'Dimensions']
        );

        $totalTime = array_sum(array_column($videoResponse->getResult(), 'duration'));
        $totalSize = array_sum(array_column($videoResponse->getResult(), 'size'));

        $this->outputLine('Total %s minutes / %s mb', [round($totalTime / 60, 2),  round($totalSize / 1024 / 1024, 2)]);
    }

    /**
     * Delete a video from cloudflare
     *
     * @param string $identifier The cloudflare video identifier
     *
     * @throws JsonException
     * @throws ConfigurationException
     * @throws TransferException
     */
    public function deleteVideoCommand(string $identifier): void
    {
        $response = $this->cloudflareClient->deleteVideo($identifier);

        if (!$response->isSuccess()) {
            $this->outputLine('<error>%s</error>', [$response->getErrorInformation()]);
        }

        $videoMetaData = $this->videoMetaDataRepository->findOneByCloudflareUid($identifier);
        if ($videoMetaData instanceof VideoMetaData) {
            $this->videoMetaDataRepository->remove($videoMetaData);
        }
    }

    /**
     * Upload all existing videos if not already present
     *
     * @throws JsonException
     * @throws IllegalObjectTypeException
     * @throws InvalidQueryException
     * @throws ConfigurationException
     * @throws TransferException
     */
    public function uploadAllCommand(): void
    {
        $videos = $this->videoRepository->findAll();
        $this->outputLine('<bold>Uploading %s files</bold>', [$videos->count()]);

        /** @var Video $video */
        foreach ($videos as $video) {
            $this->output($video->getResource()->getFilename() . ' ... ');
            $uploaded = $this->assetHandler->uploadIfNecessary($video);
            $this->outputLine($uploaded ? '<success>Uploaded</success>' : 'Not uploaded');
        }
    }
}
