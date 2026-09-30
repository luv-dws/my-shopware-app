<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCategoriesMessage;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles the scheduled category synchronization task.
 */
#[AsMessageHandler(handles: CategorySyncTask::class)]
class CategorySyncTaskHandler extends ScheduledTaskHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private MessageBusInterface $messageBus
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * Dispatches a category sync message to the queue for asynchronous processing.
     */
    public function run(): void
    {
        $this->messageBus->dispatch(
            new SyncCategoriesMessage()
        );
    }
}