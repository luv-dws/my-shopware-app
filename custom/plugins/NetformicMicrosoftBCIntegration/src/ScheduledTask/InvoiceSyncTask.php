<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Class InvoiceSyncTask
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class InvoiceSyncTask extends ScheduledTask
{
    /**
     * Executes the get task name operation.
     *
     * @internal
     */
    public static function getTaskName(): string
    {
        return 'netformic.microsoft_bc.invoice_sync';
    }

    /**
     * Executes the get default interval operation.
     *
     * @internal
     */
    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
