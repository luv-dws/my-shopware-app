<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Subscriber;

use Netformic\MicrosoftBCIntegration\Service\CustomerSyncService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;

/**
 * Subscriber responsible for synchronizing customer data from Microsoft Business Central
 * whenever a customer successfully logs into the storefront.
 */
class CustomerSubscriber implements EventSubscriberInterface
{
    /**
     * @param CustomerSyncService $customerSyncService Service handling the actual BC synchronization logic
     * @param LoggerInterface $logger Logger for recording sync failures
     * @param TranslatorInterface $translator Translator service
     */
    public function __construct(
        private readonly CustomerSyncService $customerSyncService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
        ];
    }

    /**
     * Handles the customer login event.
     *
     * Checks if the logged-in customer has a Business Central system ID in their custom fields.
     * If a valid system ID is found, it triggers a synchronization to fetch the latest
     * customer data from BC.
     *
     * @param CustomerLoginEvent $event The login event containing the authenticated customer
     */
    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $customer = $event->getCustomer();
        $customFields = $customer->getCustomFields() ?? [];
        $systemId = $customFields[PluginConfig::CUSTOM_FIELD_CUSTOMER_SYSTEM_ID->value] ?? null;

        if (!$systemId || trim($systemId) == '') {
            return;
        }

        try {
            $this->customerSyncService->syncCustomer(
                $systemId,
                $event->getContext()
            );
        } catch (\Throwable $e) {
            $this->logger->warning($this->translator->trans('netformic-bc-integration.customerSubscriber.loginSyncFailed'), [
                'customerId' => $customer->getId(),
                'systemId' => $systemId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
