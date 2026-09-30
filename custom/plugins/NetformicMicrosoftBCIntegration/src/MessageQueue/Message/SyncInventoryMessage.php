<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
/**
 * Asynchronous message for triggering batch inventory synchronization.
 * Contains the offset and batch size for the synchronization process.
 */
final readonly class SyncInventoryMessage implements AsyncMessageInterface
{
    /**
     * @internal
     * 
     * @param int $offset The pagination offset for querying products from Shopware
     * @param int $take The number of products to process in this batch
     */
    public function __construct(
        public int $offset = 0,
        public int $take = 50
    ) {}

    /**
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @return int
     */
    public function getTake(): int
    {
        return $this->take;
    }
}