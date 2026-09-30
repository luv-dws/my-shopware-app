<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Class SyncInvoicesMessage
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class SyncInvoicesMessage implements AsyncMessageInterface
{
    private ?string $nextLink;
    private int $batchSize;
    private ?string $lastSyncDate;

    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(?string $nextLink = null, int $batchSize = 50, ?string $lastSyncDate = null)
    {
        $this->nextLink = $nextLink;
        $this->batchSize = $batchSize;
        $this->lastSyncDate = $lastSyncDate;
    }

    /**
     * Executes the get next link operation.
     *
     * @internal
     */
    public function getNextLink(): ?string
    {
        return $this->nextLink;
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
