<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * Scheduled task for category synchronization.
 */
class CategorySyncTask extends ScheduledTask
{
    /**
     * Returns the unique task name.
     */
    public static function getTaskName(): string
    {
        return 'netformic.microsoft_bc.sync_categories';
    }

    /**
     * Returns the execution interval (24 hours).
     */
    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
