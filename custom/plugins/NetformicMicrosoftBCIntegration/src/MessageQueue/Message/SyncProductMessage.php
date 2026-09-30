<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to trigger a single product synchronization from Microsoft BC by system ID.
 */
final readonly class SyncProductMessage implements AsyncMessageInterface
{
    /**
     * @param string $systemId The Microsoft BC system ID of the product
     */
    public function __construct(
        public string $systemId
    ) {}
}