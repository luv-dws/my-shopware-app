<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCustomersMessage;
use Netformic\MicrosoftBCIntegration\Service\CustomerSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles customer synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncCustomersHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private CustomerSyncService $customerSyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {}

    public function __invoke(SyncCustomersMessage $message): void
    {
        $this->logger->info(
            $this->translator->trans('netformic-bc-integration.customerSync.syncBatchStarted'),
            ['nextLink' => $message->nextLink]
        );

        $this->customerSyncService->syncCustomers(
            $message->nextLink
        );
    }
}
