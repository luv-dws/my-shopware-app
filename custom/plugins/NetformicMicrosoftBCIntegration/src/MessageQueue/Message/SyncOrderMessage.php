<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Queue message used to trigger an asynchronous
 * order synchronization with Microsoft Business Central.
 */
class SyncOrderMessage implements AsyncMessageInterface
{
    /**
     * Shopware order ID that should be synchronized.
     */
    private string $orderId;
    
    /**
     * Determines whether the sync is treated as manual (skips emails).
     */
    private bool $isManual;

    /**
     * Creates a new order sync message.
     */
    public function __construct(string $orderId, bool $isManual = false)
    {
        $this->orderId = $orderId;
        $this->isManual = $isManual;
    }

    /**
     * Returns the Shopware order ID to be processed.
     */
    public function getOrderId(): string
    {
        return $this->orderId;
    }

    /**
     * Returns true if the sync is treated as manual.
     */
    public function isManual(): bool
    {
        return $this->isManual;
    }
}