<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncInventoryMessage;
use Netformic\MicrosoftBCIntegration\Service\InventorySyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Package('inventory')]
/**
 * Message handler for executing the background inventory synchronization process.
 * Receives pagination info from the queue and delegates to InventorySyncService.
 */
#[AsMessageHandler]
class SyncInventoryHandler
{
    /**
     * @internal
     */
    public function __construct(
        private InventorySyncService $inventorySyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {}

    /**
     * Executes the inventory sync based on the queued message parameters.
     *
     * @param SyncInventoryMessage $message
     * @return void
     */
    public function __invoke(SyncInventoryMessage $message): void
    {
        if ($message->getOffset() == 0) {
            $this->logger->info($this->translator->trans('netformic-bc-integration.inventorySync.syncBatchStarted'));
        }

        $this->inventorySyncService->syncInventory($message->getOffset(), $message->getTake());
    }
}