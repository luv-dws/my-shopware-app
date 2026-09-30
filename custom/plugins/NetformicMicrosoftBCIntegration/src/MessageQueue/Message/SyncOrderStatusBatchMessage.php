<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to trigger batch order status synchronization from Microsoft BC.
 */
final readonly class SyncOrderStatusBatchMessage implements AsyncMessageInterface
{
    /**
     * @param string|null $nextLink The URL to the next page of results, if any
     * @param int $take Number of records to fetch in one batch
     * @param string|null $lastSyncDate Date string to filter orders modified after this date
     */
    public function __construct(
        public ?string $nextLink,
        public int $take,
        public ?string $lastSyncDate = null
    ) {}
}
