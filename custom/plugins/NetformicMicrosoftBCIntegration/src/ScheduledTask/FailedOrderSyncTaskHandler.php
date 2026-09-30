<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Shopware\Core\Framework\Context;
use Netformic\MicrosoftBCIntegration\Service\OrderSyncService;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Package('checkout')]
/**
 * Handler for the FailedOrderSyncTask.
 * Uses a locking mechanism to prevent overlapping executions and calls
 * the OrderSyncService to find and retry failed order synchronizations.
 */
#[AsMessageHandler(handles: FailedOrderSyncTask::class)]
class FailedOrderSyncTaskHandler extends ScheduledTaskHandler
{
    /**
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private LockFactory $lockFactory,
        private OrderSyncService $orderSyncService,
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
        $lock = $this->lockFactory->createLock('bc_failed_order_sync');

        if (!$lock->acquire()) {
            $this->logger->notice($this->translator->trans('netformic-bc-integration.orderSync.failedOrderTaskAlreadyRunning'));
            return;
        }

        try {
            $this->orderSyncService->syncFailedOrders(Context::createDefaultContext());
        } finally {
            $lock->release();
        }
    }
}
