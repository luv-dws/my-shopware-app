<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncPricingMessage;
use Netformic\MicrosoftBCIntegration\Service\PricingSyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Package('inventory')]
/**
 * Message handler for executing the background pricing synchronization process.
 * Receives pagination info from the queue and delegates to PricingSyncService.
 */
#[AsMessageHandler]
class SyncPricingHandler
{
    /**
     * @internal
     */
    public function __construct(
        private PricingSyncService $pricingSyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {}

    /**
     * Executes the pricing sync based on the queued message parameters.
     *
     * @param SyncPricingMessage $message
     * @return void
     */
    public function __invoke(SyncPricingMessage $message): void
    {
        if ($message->getOffset() == 0) {
            $this->logger->info($this->translator->trans('netformic-bc-integration.pricingSync.syncBatchStarted'));
        }

        $this->pricingSyncService->syncPricing($message->getOffset(), $message->getTake());
    }
}