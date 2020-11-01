<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Command;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareClient;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;

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
                    round($videoData['size'] / 1024 / 1024, 2) ?? 0,
                    $videoData['duration'] ?? false,
                    ($videoData['input']['width'] ?? 0) . 'x' . ($videoData['input']['height'] ?? 0),
                ];
            }, $videoResponse->getResult()),
            ['UID', 'Name', 'ReadyToStream', 'Size (Mb)', 'Duration (s)', 'Dimensions']
        );
    }

    /**
     * Delete a video from cloudflare
     *
     * @param string $identifier The cloudflare video identifier
     *
     * @throws \JsonException
     * @throws \PunktDe\Cloudflare\Stream\Exception\ConfigurationException
     * @throws \PunktDe\Cloudflare\Stream\Exception\TransferException
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
}
