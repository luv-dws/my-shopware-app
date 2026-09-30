<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderStatusMessage;
use Netformic\MicrosoftBCIntegration\Service\OrderStatusSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles order status synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncOrderStatusHandler
{
    public function __construct(
        private OrderStatusSyncService $orderStatusSyncService
    ) {}

    /**
     * Processes the order status sync message for an order.
     *
     * @param SyncOrderStatusMessage $message Contains order status data to sync
     */
    public function __invoke(SyncOrderStatusMessage $message): void
    {
        $this->orderStatusSyncService->syncOrderStatus(
            $message->systemId,
            $message->orderNumber,
            $message->bcStatus
        );
    }
}
