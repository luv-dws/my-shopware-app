<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderMessage;
use Netformic\MicrosoftBCIntegration\Service\OrderSyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles queued order synchronization messages and
 * pushes Shopware orders to Microsoft Business Central.
 */
#[AsMessageHandler]
class SyncOrderHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private readonly OrderSyncService $orderSyncService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator
    ) {}

    /**
     * Processes a queued order sync message.
     *
     * @param SyncOrderMessage $message
     *
     * @return void
     */
    public function __invoke(SyncOrderMessage $message): void
    {
        $this->logger->info(
            $this->translator->trans('netformic-bc-integration.orderSync.processingSyncOrderMessage'),
            ['orderId' => $message->getOrderId()]
        );

        $this->orderSyncService->syncOrder(
            $message->getOrderId(),
            Context::createDefaultContext(),
            $message->isManual()
        );
    }
}