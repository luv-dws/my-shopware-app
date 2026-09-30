<?php declare (strict_types = 1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
/**
 * Scheduled task that triggers the recurring inventory synchronization
 * from Microsoft Business Central to Shopware.
 */
class InventorySyncTask extends ScheduledTask
{
    /**
     * @return string The name of the task
     */
    public static function getTaskName(): string
    {
        return 'netformic.microsoft_bc.inventory_sync';
    }

    /**
     * @return int The default execution interval in seconds
     */
    public static function getDefaultInterval(): int
    {
        return 3600; // 1 hour
    }
}
