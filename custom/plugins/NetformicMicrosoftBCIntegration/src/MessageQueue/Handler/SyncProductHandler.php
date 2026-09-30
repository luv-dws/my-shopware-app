<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductMessage;
use Netformic\MicrosoftBCIntegration\Service\ProductSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles single product synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncProductHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private ProductSyncService $productSyncService
    ) {}

    /**
     * Processes the product sync message and starts product synchronization.
     *
     * @param SyncProductMessage $message Contains the system ID of the product to sync.
     */
    public function __invoke(SyncProductMessage $message): void
    {
        $this->productSyncService->syncProduct(
            $message->systemId
        );
    }
}