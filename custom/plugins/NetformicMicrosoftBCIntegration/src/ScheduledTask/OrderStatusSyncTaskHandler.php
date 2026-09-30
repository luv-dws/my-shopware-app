<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderStatusBatchMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles execution of the OrderStatusSyncTask scheduled task.
 */
#[AsMessageHandler(handles: OrderStatusSyncTask::class)]
class OrderStatusSyncTaskHandler extends ScheduledTaskHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        protected EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
        private SystemConfigService $systemConfigService,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * Creates a lock to ensure only one order status synchronization process
     * can be started at a time, then dispatches the initial sync message.
     */
    public function run(): void
    {
        $lock = $this->lockFactory->createLock('bc_order_status_sync');

        if (!$lock->acquire()) {
            $this->logger->notice(
                $this->translator->trans('netformic-bc-integration.orderStatusSync.taskAlreadyRunning')
            );

            return;
        }

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', OrderStatusSyncTask::getTaskName()));
            $task = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->first();

            $lastSyncDate = null;
            if ($task && $task->getLastExecutionTime()) {
                $lastSyncDate = $task->getLastExecutionTime()->format('Y-m-d\TH:i:s\Z');
            } else {
                $lastSyncDate = (new \DateTime('-2 months'))->format('Y-m-d\TH:i:s\Z');
            }

            $batchSize = $this->systemConfigService->getInt(PluginConfig::CONFIG_ORDER_STATUS_SYNC_BATCH_SIZE->value) ?: 50;

            // Dispatch the first batch of order status syncs to the message queue
            $this->messageBus->dispatch(
                new SyncOrderStatusBatchMessage(null, $batchSize, $lastSyncDate)
            );
        } finally {
            $lock->release();
        }
    }
}
