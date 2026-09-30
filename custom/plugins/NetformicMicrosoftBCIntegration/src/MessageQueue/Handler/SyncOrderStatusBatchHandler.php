<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderStatusBatchMessage;
use Netformic\MicrosoftBCIntegration\Service\OrderStatusSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles batch order status synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncOrderStatusBatchHandler
{
    public function __construct(
        private OrderStatusSyncService $orderStatusSyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {}

    /**
     * Processes the order status batch sync message and fetches the orders to queue.
     *
     * @param SyncOrderStatusBatchMessage $message Contains pagination information for the current synchronization batch.
     */
    public function __invoke(SyncOrderStatusBatchMessage $message): void
    {
        $this->logger->info($this->translator->trans('netformic-bc-integration.orderStatusSync.syncBatchStarted'), [
            'nextLink'     => $message->nextLink,
            'take'         => $message->take
        ]);

        $this->orderStatusSyncService->syncOrderStatusBatch(
            $message->nextLink,
            $message->take,
            $message->lastSyncDate
        );
    }
}
