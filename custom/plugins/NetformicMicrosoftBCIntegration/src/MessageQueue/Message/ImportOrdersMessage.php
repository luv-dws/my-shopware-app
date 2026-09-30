<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Class ImportOrdersMessage
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class ImportOrdersMessage implements AsyncMessageInterface
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private ?string $skipToken = null,
        private int $batchSize = 50,
        private ?string $lastSyncDate = null
    ) {}

    /**
     * Executes the get skip token operation.
     *
     * @internal
     */
    public function getSkipToken(): ?string
    {
        return $this->skipToken;
    }

    /**
     * Executes the get batch size operation.
     *
     * @internal
     */
    public function getBatchSize(): int
    {
        return $this->batchSize;
    }

    /**
     * Executes the get last sync date operation.
     *
     * @internal
     */
    public function getLastSyncDate(): ?string
    {
        return $this->lastSyncDate;
    }
}