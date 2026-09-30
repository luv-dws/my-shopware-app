<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to defer database upserts for product stock and pricing asynchronously.
 */
final readonly class SyncProductUpsertMessage implements AsyncMessageInterface
{
    /**
     * @param array $upsertData Array of product upsert payloads for Shopware DAL
     */
    public function __construct(
        public array $upsertData
    ) {}
}
