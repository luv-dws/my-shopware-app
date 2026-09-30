<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductUpsertMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handles asynchronous product repository upsert operations from the queue.
 */
#[AsMessageHandler]
class SyncProductUpsertHandler
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Processes the product upsert message and persists pricing/stock data to DB.
     *
     * @param SyncProductUpsertMessage $message
     */
    public function __invoke(SyncProductUpsertMessage $message): void
    {
        if (empty($message->upsertData)) {
            return;
        }

        try {
            $this->productRepository->upsert($message->upsertData, Context::createDefaultContext());
        } catch (\Throwable $e) {
            $this->logger->error('Async product repository upsert failed: ' . $e->getMessage(), [
                'count' => count($message->upsertData),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
