<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncInventoryMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Package('inventory')]
/**
 * Handler for the InventorySyncTask.
 * Uses a locking mechanism to prevent overlapping executions and dispatches
 * the initial SyncInventoryMessage to begin the batch synchronization process.
 */
#[AsMessageHandler(handles: InventorySyncTask::class)]
class InventorySyncTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
        private SystemConfigService $systemConfigService,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * Executes the task logic when triggered.
     *
     * @return void
     */
    public function run(): void
    {
        $lock = $this->lockFactory->createLock('bc_inventory_sync');

        if (!$lock->acquire()) {
            $this->logger->notice($this->translator->trans('netformic-bc-integration.inventorySync.taskAlreadyRunning'));
            return;
        }

        try {
            $batchSize = $this->systemConfigService->getInt(PluginConfig::CONFIG_INVENTORY_SYNC_BATCH_SIZE->value) ?: 100;

            $this->messageBus->dispatch(new SyncInventoryMessage(0, $batchSize));
        } finally {
            $lock->release();
        }
    }
}