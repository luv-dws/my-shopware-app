<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCustomerMessage;
use Netformic\MicrosoftBCIntegration\Service\CustomerSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles single customer synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncCustomerHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private CustomerSyncService $customerSyncService
    ) {}

    /**
     * Processes the customer sync message and starts customer synchronization.
     *
     * @param SyncCustomerMessage $message Contains the system ID of the customer to sync.
     */
    public function __invoke(SyncCustomerMessage $message): void
    {
        $this->customerSyncService->syncCustomer(
            $message->systemId
        );
    }
}
