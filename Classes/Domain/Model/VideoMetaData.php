<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream\Domain\Model;

/*
 *  (c) 2020 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Doctrine\ORM\Mapping as ORM;
use Neos\Media\Domain\Model\Video;
use PunktDe\Cloudflare\Stream\Cloudflare\CloudflareResponse;
use PunktDe\Cloudflare\Stream\Exception\TransferException;

/**
 * @Flow\Entity
 */
class VideoMetaData
{
    /**
     * @var string
     */
    protected string $cloudflareUid = '';

    /**
     * @var Video
     * @ORM\OneToOne(cascade={"PERSIST", "REMOVE"}, orphanRemoval=true)
     */
    protected ?Video $video = null;

    /**
     * @var string
     */
    protected string $thumbnailUri = '';

    /**
     * @var string
     */
    protected string $hlsUri = '';

    /**
     * @var string
     */
    protected string $dashUri = '';

    public static function fromCloudflareResponse(CloudflareResponse $response): self
    {
        $result = $response->getResult();

        if (!isset($result['uid'])) {
            throw new TransferException('The video UID was not set in the cloudflare response. Errors: ' . $response->getErrorInformation(), 1604473536);
        }

        $videoMetaData = new static();
        $videoMetaData->setValuesFromCloudflareResponse($response);

        return $videoMetaData;
    }

    public function setValuesFromCloudflareResponse(CloudflareResponse $response): void
    {
        $result = $response->getResult();
        $this->setCloudflareUid($result['uid']);
        $this->setDashUri($result['playback']['dash'] ?? '');
        $this->setHlsUri($result['playback']['hls'] ?? '');
        $this->setThumbnailUri($result['thumbnail'] ?? '');
    }

    /**
     * @return string
     */
    public function getCloudflareUid(): string
    {
        return $this->cloudflareUid;
    }

    /**
     * @param string $cloudflareUid
     */
    public function setCloudflareUid(string $cloudflareUid): void
    {
        $this->cloudflareUid = $cloudflareUid;
    }

    /**
     * @return Video
     */
    public function getVideo(): Video
    {
        return $this->video;
    }

    /**
     * @param Video $video
     */
    public function setVideo(Video $video): void
    {
        $this->video = $video;
    }

    /**
     * @return string
     */
    public function getThumbnailUri(): string
    {
        return $this->thumbnailUri;
    }

    /**
     * @param string $thumbnailUri
     */
    public function setThumbnailUri(string $thumbnailUri): void
    {
        $this->thumbnailUri = $thumbnailUri;
    }

    /**
     * @return string
     */
    public function getHlsUri(): string
    {
        return $this->hlsUri;
    }

    /**
     * @param string $hlsUri
     */
    public function setHlsUri(string $hlsUri): void
    {
        $this->hlsUri = $hlsUri;
    }

    /**
     * @return string
     */
    public function getDashUri(): string
    {
        return $this->dashUri;
    }

    /**
     * @param string $dashUri
     */
    public function setDashUri(string $dashUri): void
    {
        $this->dashUri = $dashUri;
    }
}
