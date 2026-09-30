<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to trigger product synchronization from Microsoft BC.
 */
final readonly class SyncProductsMessage implements AsyncMessageInterface
{
    /**
     * @param string|null $nextLink The URL to the next page of results, if any
     */
    public function __construct(
        public ?string $nextLink = null
    ) {}
}