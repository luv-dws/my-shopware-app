<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncInvoicesMessage;
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

#[AsMessageHandler(handles: InvoiceSyncTask::class)]
/**
 * Class InvoiceSyncTaskHandler
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class InvoiceSyncTaskHandler extends ScheduledTaskHandler
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
        private SystemConfigService $systemConfigService,
        private readonly TranslatorInterface $translator
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
        $lock = $this->lockFactory->createLock('bc_invoice_sync');

        if (!$lock->acquire()) {
            $this->logger->notice($this->translator->trans('netformic-bc-integration.invoiceSync.taskAlreadyRunning'));
            return;
        }

        try {
            // Fetch the last execution time of the task
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('name', InvoiceSyncTask::getTaskName()));
            $task = $this->scheduledTaskRepository->search($criteria, Context::createDefaultContext())->first();

            $lastSyncDate = null;
            if ($task && $task->getLastExecutionTime()) {
                // Formatting to ISO8601 for Business Central OData
                $lastSyncDate = $task->getLastExecutionTime()->format('Y-m-d\TH:i:s\Z');
            } else {
                // Fallback to 2 months ago if never executed
                $lastSyncDate = (new \DateTime('-2 months'))->format('Y-m-d\TH:i:s\Z');
            }

            $batchSize = $this->systemConfigService->getInt('NetformicMicrosoftBCIntegration.config.invoiceSyncBatchSize') ?: 50;
            $this->messageBus->dispatch(new SyncInvoicesMessage(null, $batchSize, $lastSyncDate));
        } finally {
            $lock->release();
        }
    }
}
