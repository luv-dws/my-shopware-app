<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
/**
 * Scheduled task that triggers the retry sync of orders 
 * that failed to export to Microsoft Business Central.
 */
class FailedOrderSyncTask extends ScheduledTask
{
    /**
     * @return string The name of the task
     */
    public static function getTaskName(): string
    {
        return 'netformic.microsoft_bc.failed_order_sync';
    }

    /**
     * @return int The default execution interval in seconds
     */
    public static function getDefaultInterval(): int
    {
        // Run every 10 minutes
        return 1200;
    }
}
