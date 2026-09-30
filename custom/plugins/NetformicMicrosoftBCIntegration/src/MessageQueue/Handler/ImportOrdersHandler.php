<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\ImportOrdersMessage;
use Netformic\MicrosoftBCIntegration\Service\OrderImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
/**
 * Class ImportOrdersHandler
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class ImportOrdersHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private OrderImportService $orderImportService,
        private LoggerInterface $logger
    ) {}

    /**
     * Executes the __invoke operation.
     *
     * @internal
     */
    public function __invoke(ImportOrdersMessage $message): void
    {
        $this->logger->info('Processing ImportOrdersMessage queue worker...');

        try {
            $this->orderImportService->sync(
                $message->getSkipToken(),
                $message->getBatchSize(),
                $message->getLastSyncDate()
            );
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process order import queue.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e; // requeue
        }
    }
}