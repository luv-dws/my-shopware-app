<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * Message used to trigger category synchronization from Microsoft BC.
 */
class SyncCategoriesMessage implements AsyncMessageInterface
{
}