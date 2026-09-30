<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCustomersMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles execution of the CustomerSyncTask scheduled task.
 */
#[AsMessageHandler(handles: CustomerSyncTask::class)]
class CustomerSyncTaskHandler extends ScheduledTaskHandler
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
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * Creates a lock to ensure only one customer synchronization process
     * can be started at a time, then dispatches the initial sync message.
     */
    public function run(): void
    {
        // Prevent multiple customer sync processes from running simultaneously
        $lock = $this->lockFactory->createLock('bc_customer_sync');

        if (!$lock->acquire()) {
            $this->logger->notice(
                $this->translator->trans('netformic-bc-integration.customerSync.taskAlreadyRunning')
            );

            return;
        }

        try {
            // Dispatch the first batch of customers to the message queue
            // null acts as the initial nextLink
            $this->messageBus->dispatch(
                new SyncCustomersMessage(null)
            );
        } finally {
            // Always release the lock
            $lock->release();
        }
    }
}
