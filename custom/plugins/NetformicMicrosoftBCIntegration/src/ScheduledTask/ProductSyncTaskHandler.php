<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductsMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles execution of the ProductSyncTask scheduled task.
 */
#[AsMessageHandler(handles: ProductSyncTask::class)]
class ProductSyncTaskHandler extends ScheduledTaskHandler
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
     * Creates a lock to ensure only one product synchronization process
     * can be started at a time, then dispatches the initial sync message.
     */
    public function run(): void
    {
        // Prevent multiple product sync processes from running simultaneously
        $lock = $this->lockFactory->createLock('bc_product_sync');

        if (!$lock->acquire()) {
            $this->logger->notice(
                $this->translator->trans('netformic-bc-integration.productSync.taskAlreadyRunning')
            );

            return;
        }

        try {
            // Dispatch the first batch of products to the message queue
            // null acts as the initial nextLink
            $this->messageBus->dispatch(
                new SyncProductsMessage(null)
            );
        } finally {
            // Always release the lock
            $lock->release();
        }
    }
}