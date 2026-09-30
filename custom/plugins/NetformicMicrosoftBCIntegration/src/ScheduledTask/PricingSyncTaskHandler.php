<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncPricingMessage;
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
 * Handler for the PricingSyncTask.
 * Uses a locking mechanism to prevent overlapping executions and dispatches
 * the initial SyncPricingMessage to begin the batch synchronization process.
 */
#[AsMessageHandler(handles: PricingSyncTask::class)]
class PricingSyncTaskHandler extends ScheduledTaskHandler
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
        $lock = $this->lockFactory->createLock('bc_pricing_sync');

        if (!$lock->acquire()) {
            $this->logger->notice($this->translator->trans('netformic-bc-integration.pricingSync.taskAlreadyRunning'));
            return;
        }

        try {
            $batchSize = $this->systemConfigService->getInt(PluginConfig::CONFIG_PRICING_SYNC_BATCH_SIZE->value) ?: 100;

            $this->messageBus->dispatch(new SyncPricingMessage(0, $batchSize));
        } finally {
            $lock->release();
        }
    }
}