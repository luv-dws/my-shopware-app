<?php declare (strict_types = 1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
/**
 * Scheduled task that triggers the recurring pricing synchronization
 * from Microsoft Business Central to Shopware.
 */
class PricingSyncTask extends ScheduledTask
{
    /**
     * @return string The name of the task
     */
    public static function getTaskName(): string
    {
        return 'netformic.microsoft_bc.pricing_sync';
    }

    /**
     * @return int The default execution interval in seconds
     */
    public static function getDefaultInterval(): int
    {
        return 86400; // 24 hours
    }
}
