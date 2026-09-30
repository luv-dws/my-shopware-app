<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Mail\Service\AbstractMailService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class OrderSyncService
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class OrderSyncService
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private BcApiClient $bcApiClient,
        private LoggerInterface $logger,
        private EntityRepository $orderRepository,
        private EntityRepository $productRepository,
        private SystemConfigService $systemConfigService,
        private AbstractMailService $mailService,
        private EntityRepository $mailTemplateRepository,
        private MessageBusInterface $messageBus,
        private TranslatorInterface $translator
    ) {}

    /**
     * Finds orders that failed to sync to Business Central and re-dispatches them to the queue.
     */
    public function syncFailedOrders(Context $context): void
    {
        try {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customFields.' . PluginConfig::CUSTOM_FIELD_ORDER_SYSTEM_ID->value, null));

            $orderIds = $this->orderRepository->searchIds($criteria, $context)->getIds();

            if (empty($orderIds)) {
                return;
            }

            foreach ($orderIds as $orderId) {
                $this->messageBus->dispatch(new SyncOrderMessage($orderId, true));
            }

            $this->logger->info($this->translator->trans('netformic-bc-integration.orderSync.dispatchedSyncRetries', ['%count%' => count($orderIds)]));
        } catch (\Throwable $e) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderSync.retryTaskFailed'), [
                'orderIds' => $orderIds,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Synchronizes a single order to Business Central.
     *
     * @param string $orderId
     * @param Context $context
     * @param bool $isManual
     *
     * @return array{success: bool, error: ?string}
     */
    public function syncOrder(string $orderId, Context $context, bool $isManual = false): array
    {
        // Load the order together with all data required for BC synchronization
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('lineItems');
        $criteria->addAssociation('orderCustomer');
        $criteria->addAssociation('billingAddress.country');
        $criteria->addAssociation('billingAddress.countryState');
        $criteria->addAssociation('deliveries.shippingOrderAddress.country');
        $criteria->addAssociation('deliveries.shippingOrderAddress.countryState');
        $criteria->addAssociation('deliveries.shippingMethod');
        $criteria->addAssociation('transactions.paymentMethod');

        $order = $this->orderRepository->search($criteria, $context)->first();

        // Stop processing if the order does not exists
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        $customer = $order->getOrderCustomer();

        // BC orders require a customer reference
        if (!$customer) {
            return ['success' => false, 'error' => 'No customer found for this order.'];
        }

        $customerNumber = $customer->getCustomerNumber();

        $billingAddress = $order->getBillingAddress();
        $shippingAddress = null;
        if ($order->getDeliveries() && $order->getDeliveries()->count() > 0) {
            $shippingAddress = $order->getDeliveries()->first()->getShippingOrderAddress();
        }
        $shippingAddress = $shippingAddress ?: $billingAddress;

        $sellToState = '';
        if ($billingAddress && $billingAddress->getCountryState()) {
            $sellToState = $billingAddress->getCountryState()->getShortCode() ?: '';
            if (strpos($sellToState, '-') !== false) {
                $sellToState = substr($sellToState, strrpos($sellToState, '-') + 1);
            }
        }

        $shipToState = '';
        if ($shippingAddress && $shippingAddress->getCountryState()) {
            $shipToState = $shippingAddress->getCountryState()->getShortCode() ?: '';
            if (strpos($shipToState, '-') !== false) {
                $shipToState = substr($shipToState, strrpos($shipToState, '-') + 1);
            }
        }

        $shippingCustomFields = $shippingAddress ? ($shippingAddress->getCustomFields() ?? []) : [];
        $fflExpiry = '0001-01-01';
        $expiryVal = $shippingCustomFields[PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_EXPIRY->value] ?? null;
        if ($expiryVal) {
            try {
                if ($expiryVal instanceof \DateTimeInterface) {
                    $fflExpiry = $expiryVal->format('Y-m-d');
                } else {
                    $expiryDateTime = new \DateTime((string) $expiryVal);
                    $fflExpiry = $expiryDateTime->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Keep default '0001-01-01'
            }
        }

        $ffl4473 = (string) ($shippingCustomFields[PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_DOCUMENT_CODE->value] ?? '');

        $orderCustomFields = $order->getCustomFields() ?? [];
        $isDropship = !empty($orderCustomFields['netformic_order_is_dropship']);

        // Build the base BC order payload
        $orderData = [
            'documentType' => 'Order',
            'orderDate' => $order->getOrderDate()->format('Y-m-d'),
            'customerNumber' => $customerNumber,
            'shipToName' => $shippingAddress ? trim($shippingAddress->getFirstName() . ' ' . $shippingAddress->getLastName()) : '',
            'sellToAddressLine1' => $billingAddress ? $billingAddress->getStreet() : '',
            'sellToCity' => $billingAddress ? $billingAddress->getCity() : '',
            'sellToState' => $sellToState,
            'sellToPostCode' => $billingAddress ? $billingAddress->getZipcode() : '',
            'sellToCountry' => $billingAddress && $billingAddress->getCountry() ? $billingAddress->getCountry()->getIso() : 'US',
            'shipToAddressLine1' => $shippingAddress ? $shippingAddress->getStreet() : '',
            'shipToCity' => $shippingAddress ? $shippingAddress->getCity() : '',
            'shipToState' => $shipToState,
            'shipToPostCode' => $shippingAddress ? $shippingAddress->getZipcode() : '',
            'shipToCountry' => $shippingAddress && $shippingAddress->getCountry() ? $shippingAddress->getCountry()->getIso() : 'US',
            'fflExpiry' => $fflExpiry,
            'ffl4473' => $ffl4473,
            'dropship' => $isDropship,
            'ffl4473Required' => !empty($orderCustomFields['netformic_order_ffl_required']),
            'salesOrderLinesOrionMFG' => [],
        ];

        if ($shippingAddress && $shippingAddress->getAdditionalAddressLine1()) {
            $orderData['shipToAddressLine2'] = $shippingAddress->getAdditionalAddressLine1();
        }

        if ($billingAddress && $billingAddress->getAdditionalAddressLine1()) {
            $orderData['sellToAddressLine2'] = $billingAddress->getAdditionalAddressLine1();
        }

        $lineItems = $order->getLineItems();
        $sequence = 0;

        if ($lineItems) {
            $productIds = [];

            // Collect product IDs for a single repository query
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getType() === 'product') {
                    $productIds[] = $lineItem->getReferencedId();
                }
            }

            $products = [];

            // Load all products referenced by the order
            if (!empty($productIds)) {
                $productCriteria = new Criteria($productIds);

                $products = $this->productRepository
                    ->search($productCriteria, $context)
                    ->getElements();
            }

            foreach ($lineItems as $lineItem) {
                // Only sync actual product or adult signature fee line items
                if ($lineItem->getType() !== 'product' && $lineItem->getType() !== 'adult_signature_fee') {
                    continue;
                }

                if ($lineItem->getType() === 'product') {
                    $productId = $lineItem->getReferencedId();
                    $product = $products[$productId] ?? null;

                    $productNumber = '';
                    if ($product) {
                        $productNumber = $product->getProductNumber();
                    } else {
                        $payload = $lineItem->getPayload();
                        $productNumber = $payload['productNumber'] ?? $lineItem->getReferencedId();
                    }

                    $sequence += 10000;
                    $orderData['salesOrderLinesOrionMFG'][] = [
                        'documentType' => 'Order',
                        'sequence' => $sequence,
                        'type' => 'Item',
                        'lineObjectNumber' => $productNumber,
                        'description' => $lineItem->getLabel() ?: '',
                        'quantity' => $lineItem->getQuantity(),
                        'unitPrice' => $lineItem->getUnitPrice(),
                    ];
                } else {
                    // Non-product line items (like adult signature fee) do not send itemId, only description
                    $sequence += 10000;
                    $orderData['salesOrderLinesOrionMFG'][] = [
                        'documentType' => 'Order',
                        'sequence' => $sequence,
                        'description' => $lineItem->getLabel() ?: '',
                        'quantity' => $lineItem->getQuantity(),
                        'unitPrice' => $lineItem->getUnitPrice(),
                    ];
                }
            }
        }

        // Add shipping costs as a line item if present and greater than 0
        $shippingCosts = $order->getShippingCosts();
        if ($shippingCosts && $shippingCosts->getTotalPrice() > 0.0) {
            $shippingMethodName = 'Shipping Costs';
            if ($order->getDeliveries() && $order->getDeliveries()->count() > 0) {
                $delivery = $order->getDeliveries()->first();
                $shippingMethod = $delivery->getShippingMethod();
                if ($shippingMethod) {
                    $shippingMethodName = $shippingMethod->getName() ?: 'Shipping Costs';
                }
            }

            $sequence += 10000;
            $orderData['salesOrderLinesOrionMFG'][] = [
                'documentType' => 'Order',
                'sequence' => $sequence,
                'description' => $shippingMethodName,
                'quantity' => 1,
                'unitPrice' => $shippingCosts->getTotalPrice(),
            ];
        }

        // Add order comment as salesorder lines        
        $comment = $order->getCustomerComment();
        if(!empty($comment)){
            $sequence += 10000;
            $orderData['salesOrderLinesOrionMFG'][] = [
                'documentType' => 'Order',
                'sequence' => $sequence,
                'lineType' => 'Comment',
                'description' => $comment,
            ];
        }

        // Skip synchronization if no valid order lines were found
        if (empty($orderData['salesOrderLinesOrionMFG'])) {
            return ['success' => false, 'error' => 'No valid order lines found to sync.'];
        }

        try {
            // Create the order in Microsoft Business Central
            $response = $this->bcApiClient->pushOrder($orderData);
            $bcOrderId = $response['systemId'] ?? null;
            $bcOrderNumber = $response['number'] ?? null;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderSync.syncFailed'), [
                'orderId' => $order->getId(),
                'orderNumber' => $order->getOrderNumber(),
                'error' => $errorMsg,
            ]);

            // Mark the order as permanently failed so the scheduled task stops retrying it
            $this->orderRepository->update([
                [
                    'id' => $order->getId(),
                    'customFields' => [PluginConfig::CUSTOM_FIELD_ORDER_SYSTEM_ID->value => 'FAILED'],
                ],
            ], $context);

            if (!$isManual) {
                $this->sendFailureEmail($order, $customer, $context);
                throw $e;
            }

            return ['success' => false, 'error' => $errorMsg];
        }

        // Store the BC order ID and BC order number on the Shopware order for future reference
        if ($bcOrderId) {
            // Validate
            $updatedCustomFields = $order->getCustomFields();
            $updatedCustomFields = [
                PluginConfig::CUSTOM_FIELD_ORDER_SYSTEM_ID->value => $bcOrderId,
            ];

            if ($bcOrderNumber) {
                $updatedCustomFields[PluginConfig::CUSTOM_FIELD_BC_ORDER_NUMBER->value] = (string) $bcOrderNumber;
            }

            $this->orderRepository->update([
                [
                    'id' => $order->getId(),
                    'customFields' => $updatedCustomFields,
                ],
            ], $context);
            $order->setCustomFields(array_replace(
                $order->getCustomFields() ?? [],
                $updatedCustomFields
            ));

            $this->syncPayFabricTransaction($order, $context);
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * Sends a failure notification email to the customer if configured.
     *
     * @param OrderEntity $order
     * @param OrderCustomerEntity $customer
     * @param Context $context
     *
     * @return void
     */
    private function sendFailureEmail(OrderEntity $order, OrderCustomerEntity $customer, Context $context): void
    {
        $enableEmail = $this->systemConfigService->getBool(PluginConfig::CONFIG_ENABLE_ORDER_SYNC_FAILURE_EMAIL->value);
        $templateId = $this->systemConfigService->getString(PluginConfig::CONFIG_ORDER_SYNC_FAILURE_EMAIL_TEMPLATE_ID->value);

        if (!$enableEmail || !$templateId || !$customer->getEmail()) {
            return;
        }

        $criteria = new Criteria([$templateId]);
        $mailTemplate = $this->mailTemplateRepository->search($criteria, $context)->first();

        if (!$mailTemplate) {
            return;
        }

        $data = [
            'recipients' => [
                $customer->getEmail() => $customer->getFirstName() . ' ' . $customer->getLastName()
            ],
            'salesChannelId' => $order->getSalesChannelId(),
            'templateId' => $templateId,
            'subject' => $mailTemplate->getTranslation('subject'),
            'contentHtml' => $mailTemplate->getTranslation('contentHtml'),
            'contentPlain' => $mailTemplate->getTranslation('contentPlain'),
            'senderName' => $mailTemplate->getTranslation('senderName'),
        ];

        try {
            $this->mailService->send($data, $context, [
                'order' => $order,
                'customer' => $customer,
            ]);
        } catch (\Throwable $mailException) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderSync.emailSendFailed'), [
                    'orderId' => $order->getId(),
                    'orderNumber' => $order->getOrderNumber(),
                    'error' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * Checks if the order has a PayFabric transaction and pushes the link to Business Central.
     *
     * @param OrderEntity $order
     * @param Context|null $context
     *
     * @return void
     */
    public function syncPayFabricTransaction(OrderEntity $order, ?Context $context = null): void
    {
        $orderCustomFields = $order->getCustomFields() ?? [];
        if (!empty($orderCustomFields[PluginConfig::CUSTOM_FIELD_IS_PAYFABRIC_SYNCED->value])) {
            return;
        }

        $documentId = $orderCustomFields[PluginConfig::CUSTOM_FIELD_BC_ORDER_NUMBER->value] ?? null;

        if (!$documentId) {
            return;
        }

        $transactions = $order->getTransactions();
        if (!$transactions || $transactions->count() == 0) {
            return;
        }

        $trxKey = null;

        foreach ($transactions as $transaction) {
            $customFields = $transaction->getCustomFields() ?? [];
            if (!empty($customFields[PluginConfig::CUSTOM_FIELD_PAYFABRIC_TRX_KEY->value])) {
                $trxKey = (string) $customFields[PluginConfig::CUSTOM_FIELD_PAYFABRIC_TRX_KEY->value];
                break;
            }
        }

        if (!$trxKey) {
            return;
        }

        try {
            $this->bcApiClient->pushPayFabricTransaction([
                'PFTransactionKey' => $trxKey,
                'DocumentType'     => 'Order',
                'DocumentID'       => (string) $documentId,
                'Service'          => 'PayFabric',
            ]);

            $this->orderRepository->update([
                [
                    'id' => $order->getId(),
                    'customFields' => [
                        PluginConfig::CUSTOM_FIELD_IS_PAYFABRIC_SYNCED->value => true,
                    ],
                ],
            ], $context);

        } catch (\Throwable $e) {
            // Log warning but do not fail order sync since the order itself was already created in BC
            $this->logger->warning($this->translator->trans('netformic-bc-integration.orderSync.payfabricLinkFailed'), [
                    'orderId'    => $order->getId(),
                    'orderNumber'=> $order->getOrderNumber(),
                    'documentId' => $documentId,
                    'trxKey'     => $trxKey,
                    'error'      => $e->getMessage(),
            ]);
        }
    }
}
