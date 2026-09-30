<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to trigger a single customer synchronization from Microsoft BC by system ID.
 */
final readonly class SyncCustomerMessage implements AsyncMessageInterface
{
    /**
     * @param string $systemId The Microsoft BC system ID of the customer
     */
    public function __construct(
        public string $systemId
    ) {}
}
