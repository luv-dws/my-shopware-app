<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCategoriesMessage;
use Netformic\MicrosoftBCIntegration\Service\CategorySyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles category synchronization messages from the queue.
 */
#[AsMessageHandler]
class SyncCategoriesHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private CategorySyncService $categorySyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {}

    /**
     * Processes the category sync message and starts category synchronization.
     */
    public function __invoke(SyncCategoriesMessage $message): void
    {
        $this->logger->info($this->translator->trans('netformic-bc-integration.categorySync.syncStarted'));

        $this->categorySyncService->syncCategories();
    }
}