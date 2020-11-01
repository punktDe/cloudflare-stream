<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Domain\Repository;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Flow\Persistence\Doctrine\Repository;
use Neos\Media\Domain\Model\Video;
use PunktDe\Cloudflare\Stream\Domain\Model\VideoMetaData;

/**
 * @Flow\Scope("singleton")
 *
 * @method findOneByCloudflareUid(string $identifier): ?VideoMetaData
 */
class VideoMetaDataRepository extends Repository
{

    public function findOneByVideo(Video $video): ?VideoMetaData
    {
        return parent::findOneByVideo($video);
    }
}
