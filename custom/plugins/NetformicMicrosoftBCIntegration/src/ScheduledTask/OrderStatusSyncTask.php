<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Scheduled task definition for order status synchronization.
 */
class OrderStatusSyncTask extends ScheduledTask
{
    /**
     * Unique technical name of the scheduled task.
     */
    public static function getTaskName(): string
    {
        return 'netformic.microsoft_bc.order_status_sync';
    }

    /**
     * Defines how often the task should run.
     *
     * 86400 seconds = 24 hours.
     */
    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
