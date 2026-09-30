<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductsMessage;
use Netformic\MicrosoftBCIntegration\Service\ProductSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles product synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncProductsHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private ProductSyncService $productSyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {}

    /**
     * Processes the product sync message and starts product synchronization.
     *
     * @param SyncProductsMessage $message Contains pagination information for the current synchronization batch.
     */
    public function __invoke(SyncProductsMessage $message): void
    {
        $this->logger->info(
            $this->translator->trans('netformic-bc-integration.productSync.syncBatchStarted'),
            ['nextLink' => $message->nextLink]
        );

        $this->productSyncService->syncProducts(
            $message->nextLink
        );
    }
}