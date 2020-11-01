<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Eel;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Media\Domain\Model\Video;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;
use PunktDe\Cloudflare\Stream\Domain\Repository\VideoMetaDataRepository;

class StreamHelper implements ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var VideoMetaDataRepository
     */
    protected $videoMetaDataRepository;

    /**
     * @param Video|null $video
     * @return VideoMetaData|null
     */
    public function getVideoMetaData(?Video $video): ?VideoMetaData
    {
        if ($video === null) {
            return null;
        }

        return $this->videoMetaDataRepository->findOneByVideo($video);
    }

    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
