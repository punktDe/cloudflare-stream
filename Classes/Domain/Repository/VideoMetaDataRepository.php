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
 * @method findOneByVideo(Video $video): ?VideoMetaData
 * @method findOneByCloudflareUid(string $identifier): ?VideoMetaData
 */
class VideoMetaDataRepository extends Repository
{
    /**
     * @return Video[]
     */
    public function findVideosWithoutMetaData(): array
    {
        return $this->entityManager->createQuery(sprintf(
            'SELECT v FROM %s v WHERE NOT EXISTS (SELECT m FROM %s m WHERE m.video = v)',
            Video::class,
            VideoMetaData::class
        ))->getResult();
    }

    /**
     * Checks the database directly, as the given video might be deleted by another process meanwhile
     */
    public function videoExists(string $videoIdentifier): bool
    {
        return (int)$this->entityManager->createQuery(sprintf(
            'SELECT COUNT(v) FROM %s v WHERE v.Persistence_Object_Identifier = :identifier',
            Video::class
        ))->setParameter('identifier', $videoIdentifier)->getSingleScalarResult() > 0;
    }
}
