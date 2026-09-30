<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\ImportOrdersMessage;
use DateTimeImmutable;

/**
 * Service to handle synchronization of orders from Microsoft Business Central to Shopware.
 */
class OrderImportService
{
    /**
     * @internal
     */
    public function __construct(
        private BcApiClient $bcApiClient,
        private EntityRepository $orderRepository,
        private EntityRepository $customerRepository,
        private EntityRepository $currencyRepository,
        private EntityRepository $salesChannelRepository,
        private EntityRepository $stateMachineStateRepository,
        private EntityRepository $shippingMethodRepository,
        private EntityRepository $paymentMethodRepository,
        private EntityRepository $countryRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        private EntityRepository $productRepository
    ) {}

    /**
     * Fetches a chunk of orders from Business Central and processes them.
     * Dispatches a message to fetch the next chunk if pagination is required.
     *
     * @param string|null $skipToken OData skip token for pagination
     * @param int $batchSize Number of orders to fetch per request
     * @param string|null $lastSyncDate Date string to filter orders modified after this date
     */
    public function sync(?string $skipToken, int $batchSize, ?string $lastSyncDate): void
    {


        $endpoint = 'salesOrders?$expand=salesOrderLines';
        if ($lastSyncDate) {
            $endpoint .= '&$filter=lastModifiedDateTime%20ge%20' . urlencode($lastSyncDate);
        }

        if ($skipToken) {
            $endpoint .= '&$skiptoken=' . $skipToken;
        }

        $headers = ['Prefer' => 'odata.maxpagesize=' . $batchSize];
        $data = $this->bcApiClient->request('GET', $endpoint, [], $headers);

        if (empty($data['value'])) {

            return;
        }

        $context = Context::createDefaultContext();
        $orders = $data['value'];

        foreach ($orders as $bcOrder) {
            $this->processOrder($bcOrder, $context);
        }

        if (isset($data['@odata.nextLink'])) {
            $this->dispatchNextBatch($data['@odata.nextLink'], $batchSize, $lastSyncDate);
        }
    }

    /**
     * Processes a single order from Business Central and creates it in Shopware.
     *
     * @param array<string, mixed> $bcOrder The order data from BC
     * @param Context $context The Shopware context
     */
    private function processOrder(array $bcOrder, Context $context): void
    {
        $bcId = $bcOrder['id'];
        $orderNumber = $bcOrder['number'];

        if ($this->orderExists($orderNumber, $bcId, $context)) {
            return;
        }

        if ($this->hasMissingCoreData($bcOrder)) {
            $this->logger->warning("Skipping order $orderNumber: Missing core data (line items, prices, or addresses).");
            return;
        }

        $bcCustomerId = $bcOrder['customerId'] ?? '';
        $customer = $this->getCustomerByBcId($bcCustomerId, $context);

        if (!$customer) {
            $this->logger->warning("Skipping order $orderNumber: Customer with BC ID $bcCustomerId not found in Shopware.");
            return;
        }

        $dependencies = $this->resolveDependencies($customer, $context);
        if ($this->hasMissingDependencies($dependencies)) {
            $this->logger->error("Missing required Shopware default dependencies to create order $orderNumber");
            return;
        }

        $orderPayload = $this->buildOrderPayload($bcOrder, $customer, $dependencies, $context);

        try {
            $this->orderRepository->create([$orderPayload], $context);

        } catch (\Exception $e) {
            $this->logger->error("Failed to create order $orderNumber: " . $e->getMessage());
        }
    }

    /**
     * Checks if an order already exists in Shopware. If it does, updates the BC system ID and returns true.
     *
     * @param string $orderNumber The order number to search for
     * @param string $bcId The Business Central system ID
     * @param Context $context The Shopware context
     * @return bool True if the order exists, false otherwise
     */
    private function orderExists(string $orderNumber, string $bcId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('orderNumber', $orderNumber));
        $existingOrder = $this->orderRepository->searchIds($criteria, $context)->firstId();

        if ($existingOrder) {
            $this->orderRepository->update([[
                'id' => $existingOrder,
                'customFields' => [
                    'netformic_order_system_id' => $bcId
                ]
            ]], $context);
            
            return true;
        }

        return false;
    }

    /**
     * Checks if the BC order is missing essential data required for import.
     *
     * @param array<string, mixed> $bcOrder The Business Central order
     * @return bool True if data is missing, false otherwise
     */
    private function hasMissingCoreData(array $bcOrder): bool
    {
        if (empty($bcOrder['salesOrderLines'])) {
            return true;
        }

        if (!isset($bcOrder['totalAmountIncludingTax']) || !isset($bcOrder['totalAmountExcludingTax'])) {
            return true;
        }

        $hasBillingAddress = !empty($bcOrder['billToAddressLine1']) || !empty($bcOrder['sellToAddressLine1']);
        $hasShippingAddress = !empty($bcOrder['shipToAddressLine1']) || !empty($bcOrder['sellToAddressLine1']);

        if (!$hasBillingAddress || !$hasShippingAddress) {
            return true;
        }

        return false;
    }

    /**
     * Retrieves a Shopware customer by their Business Central system ID.
     *
     * @param string $bcCustomerId The Business Central customer system ID
     * @param Context $context The Shopware context
     * @return CustomerEntity|null The customer entity, or null if not found
     */
    private function getCustomerByBcId(string $bcCustomerId, Context $context): ?CustomerEntity
    {
        $customerCriteria = new Criteria();
        $customerCriteria->addFilter(new EqualsFilter('customFields.netformic_customer_system_id', $bcCustomerId));
        $customerId = $this->customerRepository->searchIds($customerCriteria, $context)->firstId();

        if (!$customerId) {
            return null;
        }

        $customerFetchCriteria = new Criteria([$customerId]);
        $customerFetchCriteria->addAssociation('defaultShippingAddress');
        
        /** @var CustomerEntity|null $customer */
        $customer = $this->customerRepository->search($customerFetchCriteria, $context)->first();
        
        return $customer;
    }

    /**
     * Resolves all necessary IDs (states, methods, channels) required to create an order.
     *
     * @param CustomerEntity $customer The Shopware customer
     * @param Context $context The Shopware context
     * @return array<string, string|null> An associative array of dependency IDs
     */
    private function resolveDependencies(CustomerEntity $customer, Context $context): array
    {
        return [
            'currencyId' => $this->currencyRepository->searchIds(new Criteria(), $context)->firstId(),
            'salesChannelId' => $this->getSalesChannelId($context),
            'orderStateId' => $this->getStateMachineStateId('order.state', 'open', $context),
            'transactionStateId' => $this->getStateMachineStateId('order_transaction.state', 'open', $context),
            'deliveryStateId' => $this->getStateMachineStateId('order_delivery.state', 'open', $context),
            'shippingMethodId' => $this->getActiveMethodId($this->shippingMethodRepository, $context),
            'paymentMethodId' => $this->getActiveMethodId($this->paymentMethodRepository, $context),
            'countryId' => $this->resolveCountryId($customer, $context),
        ];
    }

    /**
     * Helper to get the storefront sales channel ID.
     */
    private function getSalesChannelId(Context $context): ?string
    {
        $scCriteria = new Criteria();
        $scCriteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        return $this->salesChannelRepository->searchIds($scCriteria, $context)->firstId() ?? Defaults::SALES_CHANNEL_TYPE_STOREFRONT;
    }

    /**
     * Helper to get a state machine state ID.
     */
    private function getStateMachineStateId(string $stateMachineName, string $stateName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachineName));
        $criteria->addFilter(new EqualsFilter('technicalName', $stateName));
        return $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();
    }

    /**
     * Helper to get an active method ID (shipping or payment).
     */
    private function getActiveMethodId(EntityRepository $repository, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->setLimit(1);
        return $repository->searchIds($criteria, $context)->firstId();
    }

    /**
     * Resolves the country ID for the order, falling back to US or the first active country.
     */
    private function resolveCountryId(CustomerEntity $customer, Context $context): ?string
    {
        $countryId = $customer->getDefaultShippingAddress() ? $customer->getDefaultShippingAddress()->getCountryId() : null;
        
        if (!$countryId) {
            $countryCriteria = new Criteria();
            $countryCriteria->addFilter(new EqualsFilter('active', true));
            $countryCriteria->addFilter(new EqualsFilter('iso', 'US'));
            $countryId = $this->countryRepository->searchIds($countryCriteria, $context)->firstId();

            if (!$countryId) {
                $fallbackCriteria = new Criteria();
                $fallbackCriteria->addFilter(new EqualsFilter('active', true));
                $fallbackCriteria->setLimit(1);
                $countryId = $this->countryRepository->searchIds($fallbackCriteria, $context)->firstId();
            }
        }
        
        return $countryId;
    }

    /**
     * Checks if any required dependencies are missing.
     *
     * @param array<string, string|null> $dependencies
     * @return bool
     */
    private function hasMissingDependencies(array $dependencies): bool
    {
        return in_array(null, $dependencies, true);
    }

    /**
     * Builds the full DAL payload required to create the order.
     *
     * @param array<string, mixed> $bcOrder
     * @param CustomerEntity $customer
     * @param array<string, string|null> $deps
     * @param Context $context
     * @return array<string, mixed>
     */
    private function buildOrderPayload(array $bcOrder, CustomerEntity $customer, array $deps, Context $context): array
    {
        $billingAddressId = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();
        $deliveryId = Uuid::randomHex();
        $transactionId = Uuid::randomHex();

        $firstName = $customer->getFirstName();
        $lastName = $customer->getLastName();
        $salutationId = $customer->getSalutationId();

        $billingAddress = $this->buildAddressPayload($billingAddressId, $bcOrder, $firstName, $lastName, $salutationId, $deps['countryId'], 'billTo');
        $shippingAddress = $this->buildAddressPayload($shippingAddressId, $bcOrder, $firstName, $lastName, $salutationId, $deps['countryId'], 'shipTo');
        
        $lineItems = $this->buildLineItemsPayload($bcOrder, $context);

        $totalExcluding = (float)($bcOrder['totalAmountExcludingTax'] ?? 0);
        $totalIncluding = (float)($bcOrder['totalAmountIncludingTax'] ?? 0);

        return [
            'id' => Uuid::randomHex(),
            'orderNumber' => $bcOrder['number'],
            'orderDateTime' => (new DateTimeImmutable($bcOrder['orderDate']))->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'currencyId' => $deps['currencyId'],
            'currencyFactor' => 1.0,
            'itemRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'totalRounding' => ['decimals' => 2, 'interval' => 0.01, 'roundForNet' => true],
            'languageId' => $context->getLanguageId(),
            'salesChannelId' => $deps['salesChannelId'],
            'stateId' => $deps['orderStateId'],
            'billingAddressId' => $billingAddressId,
            'addresses' => [$billingAddress, $shippingAddress],
            'price' => new CartPrice(
                $totalExcluding,
                $totalIncluding,
                $totalExcluding,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                CartPrice::TAX_STATE_GROSS
            ),
            'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customer->getId(),
                'salutationId' => $salutationId,
                'email' => $bcOrder['email'] ?? $customer->getEmail(),
                'firstName' => $firstName,
                'lastName' => $lastName,
            ],
            'primaryOrderDeliveryId' => $deliveryId,
            'primaryOrderTransactionId' => $transactionId,
            'deliveries' => [
                [
                    'id' => $deliveryId,
                    'stateId' => $deps['deliveryStateId'],
                    'shippingMethodId' => $deps['shippingMethodId'],
                    'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    'shippingDateEarliest' => (new DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingDateLatest' => (new DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'shippingOrderAddress' => $shippingAddress,
                ]
            ],
            'transactions' => [
                [
                    'id' => $transactionId,
                    'paymentMethodId' => $deps['paymentMethodId'],
                    'stateId' => $deps['transactionStateId'],
                    'amount' => new CalculatedPrice(
                        $totalIncluding, 
                        $totalIncluding, 
                        new CalculatedTaxCollection(), 
                        new TaxRuleCollection()
                    ),
                ]
            ],
            'lineItems' => $lineItems,
            'customFields' => [
                'netformic_order_system_id' => $bcOrder['id']
            ]
        ];
    }

    /**
     * Builds an address payload array based on BC order fields.
     */
    private function buildAddressPayload(
        string $addressId, 
        array $bcOrder, 
        string $firstName, 
        string $lastName, 
        ?string $salutationId, 
        string $countryId, 
        string $prefix
    ): array {
        return [
            'id' => $addressId,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'street' => $bcOrder[$prefix . 'AddressLine1'] ?? $bcOrder['shipToAddressLine1'] ?? 'Unknown Street',
            'city' => $bcOrder[$prefix . 'City'] ?? $bcOrder['shipToCity'] ?? 'Unknown City',
            'zipcode' => $bcOrder[$prefix . 'PostCode'] ?? $bcOrder['shipToPostCode'] ?? '00000',
            'countryId' => $countryId,
            'phoneNumber' => $bcOrder['phoneNumber'] ?? null,
        ];
    }

    /**
     * Builds the line items payload from the BC order lines.
     */
    private function buildLineItemsPayload(array $bcOrder, Context $context): array
    {
        $lineItems = [];
        if (empty($bcOrder['salesOrderLines'])) {
            return $lineItems;
        }

        foreach ($bcOrder['salesOrderLines'] as $line) {
            if (empty($line['itemId'])) {
                continue;
            }

            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customFields.netformic_product_system_id', $line['itemId']));
            $productId = $this->productRepository->searchIds($criteria, $context)->firstId();

            $lineItemPayload = [
                'id' => Uuid::randomHex(),
                'identifier' => $line['itemId'],
                'quantity' => (int) ($line['quantity'] ?? 1),
                'label' => $line['description'] ?? 'Product',
                'type' => $productId ? 'product' : 'custom',
                'price' => new CalculatedPrice(
                    (float) ($line['unitPrice'] ?? 0),
                    (float) ($line['netAmountIncludingTax'] ?? 0),
                    new CalculatedTaxCollection(),
                    new TaxRuleCollection()
                ),
                'priceDefinition' => new QuantityPriceDefinition(
                    (float) ($line['unitPrice'] ?? 0),
                    new TaxRuleCollection(),
                    (int) ($line['quantity'] ?? 1)
                )
            ];

            if ($productId) {
                $lineItemPayload['referencedId'] = $productId;
                $lineItemPayload['productId'] = $productId;
                $lineItemPayload['payload'] = [
                    'productNumber' => $line['lineObjectNumber'] ?? $line['itemId']
                ];
            }

            $lineItems[] = $lineItemPayload;
        }

        return $lineItems;
    }

    /**
     * Dispatches a message to process the next chunk of orders if a skip token is present.
     */
    private function dispatchNextBatch(string $nextUrl, int $batchSize, ?string $lastSyncDate): void
    {
        $parsedUrl = parse_url($nextUrl);
        parse_str($parsedUrl['query'] ?? '', $queryParams);

        if (isset($queryParams['$skiptoken'])) {

            $this->messageBus->dispatch(new ImportOrdersMessage($queryParams['$skiptoken'], $batchSize, $lastSyncDate));
        }
    }
}