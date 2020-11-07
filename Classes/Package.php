<?php
declare(strict_types=1);

namespace PunktDe\Cloudflare\Stream;

use Neos\Flow\Core\Booting\Sequence;
use Neos\Flow\Core\Booting\Step;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Media\Domain\Service\AssetService;
use PunktDe\Cloudflare\Stream\VideoProcessing\AssetHandler;

class Package extends BasePackage
{
    /**
     * Invokes custom PHP code directly after the package manager has been initialized.
     *
     * @param Bootstrap $bootstrap The current bootstrap
     *
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $package = $this;
        $dispatcher->connect(Sequence::class, 'afterInvokeStep', function (Step $step) use ($package, $bootstrap) {
            if ($step->getIdentifier() === 'neos.flow:objectmanagement:runtime') {
                $package->registerAssetSlots($bootstrap);
            }
        });
    }

    /**
     * Registers slots for signals in order to be able to index nodes
     *
     * @param Bootstrap $bootstrap
     */
    public function registerAssetSlots(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(AssetService::class, 'assetCreated', AssetHandler::class, 'assetCreated');
        $dispatcher->connect(AssetService::class, 'assetRemoved', AssetHandler::class, 'assetRemoved');
        $dispatcher->connect(AssetService::class, 'assetUpdated', AssetHandler::class, 'assetUpdated');
        $dispatcher->connect(AssetService::class, 'assetResourceReplaced', AssetHandler::class, 'assetResourceReplaced');
    }
}
