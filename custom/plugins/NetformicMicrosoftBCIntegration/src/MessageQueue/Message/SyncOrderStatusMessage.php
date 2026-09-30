<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to trigger order status synchronization for an order from Microsoft BC.
 */
final readonly class SyncOrderStatusMessage implements AsyncMessageInterface
{
    /**
     * @param string $systemId The Microsoft BC system ID of the order
     * @param string|null $orderNumber The order number
     * @param string $bcStatus The Business Central status string
     */
    public function __construct(
        public string $systemId,
        public ?string $orderNumber,
        public string $bcStatus
    ) {}
}
