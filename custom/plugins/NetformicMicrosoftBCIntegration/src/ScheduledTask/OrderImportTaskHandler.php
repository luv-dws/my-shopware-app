<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\ImportOrdersMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Context;

#[AsMessageHandler(handles: OrderImportTask::class)]
/**
 * Class OrderImportTaskHandler
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class OrderImportTaskHandler extends ScheduledTaskHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private LockFactory $lockFactory,
        private SystemConfigService $systemConfigService
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * Executes the run operation.
     *
     * @internal
     */
    public function run(): void
    {
        $lock = $this->lockFactory->createLock('bc_order_import');

        if (!$lock->acquire()) {
            $this->logger->notice('Order import is already running. Aborting scheduled task.');
            return;
        }

        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', OrderImportTask::getTaskName()));
            $task = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->first();

            $lastSyncDate = null;
            if ($task && $task->getLastExecutionTime()) {
                $lastSyncDate = $task->getLastExecutionTime()->format('Y-m-d\TH:i:s\Z');
            } else {
                $lastSyncDate = (new \DateTime('-2 months'))->format('Y-m-d\TH:i:s\Z');
            }

            $batchSize = $this->systemConfigService->getInt('NetformicMicrosoftBCIntegration.config.orderImportBatchSize') ?: 50;
            $this->messageBus->dispatch(new ImportOrdersMessage(null, $batchSize, $lastSyncDate));
        } finally {
            $lock->release();
        }
    }
}