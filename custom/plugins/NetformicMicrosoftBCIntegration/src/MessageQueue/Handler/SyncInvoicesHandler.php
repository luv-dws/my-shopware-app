<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\MessageQueue\Handler;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncInvoicesMessage;
use Netformic\MicrosoftBCIntegration\Service\InvoiceSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsMessageHandler(handles: SyncInvoicesMessage::class)]
/**
 * Class SyncInvoicesHandler
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class SyncInvoicesHandler
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private InvoiceSyncService $invoiceSyncService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Executes the __invoke operation.
     *
     * @internal
     */
    public function __invoke(SyncInvoicesMessage $message): void
    {
        if (!$message->getNextLink()) {
            $this->logger->info($this->translator->trans('netformic-bc-integration.invoiceSync.syncStarted'), [
                'lastSyncDate' => $message->getLastSyncDate(),
            ]);
        }

        try {
            $this->invoiceSyncService->sync(
                $message->getNextLink(),
                $message->getBatchSize(),
                $message->getLastSyncDate()
            );
        } catch (\Throwable $e) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.invoiceSync.asyncSyncFailed'), [
                'exception' => $e->getMessage(),
                'nextLink' => $message->getNextLink()
            ]);
            throw $e;
        }
    }
}
