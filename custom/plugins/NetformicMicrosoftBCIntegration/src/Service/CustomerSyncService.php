<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCustomersMessage;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncCustomerMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Context;
use Symfony\Component\Messenger\MessageBusInterface;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Shopware\Core\Defaults;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Synchronizes customers from Microsoft Business Central into Shopware.
 *
 * Customers are imported in batches and additional batches are
 * automatically queued until all records have been processed.
 */
class CustomerSyncService
{
    private ?string $defaultCustomerGroupId = null;
    private ?string $defaultSalesChannelId = null;
    private ?string $defaultPaymentMethodId = null;
    private ?string $defaultSalutationId = null;
    /**
     * @var array<string, array{id: string, iso: string}>
     */
    private array $countryCache = [];

    /**
     * @var array<string, string>
     */
    private array $countryStateIdCache = [];

    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private BcApiClient $apiClient,
        private EntityRepository $customerRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private EntityRepository $customerGroupRepository,
        private EntityRepository $salesChannelRepository,
        private EntityRepository $paymentMethodRepository,
        private EntityRepository $salutationRepository,
        private EntityRepository $countryRepository,
        private EntityRepository $countryStateRepository,
        private SystemConfigService $systemConfigService,
        private TranslatorInterface $translator
    ) {}

    /**
     * Processes a single customer batch from Microsoft BC.
     *
     * @param string|null $nextLink The pagination link for the next batch of customers
     */
    public function syncCustomers(?string $nextLink): void
    {
        $context = Context::createDefaultContext();
        $take = $this->systemConfigService->getInt(PluginConfig::CONFIG_CUSTOMER_SYNC_BATCH_SIZE->value) ?: 50;

        // Fetch a batch of customers from BC
        $response = $this->apiClient->fetchCustomers($nextLink, $take);
        $bcCustomers = $response['data'] ?? [];
        $newNextLink = $response['nextLink'] ?? null;

        if (empty($bcCustomers)) {
            $this->logger->info(
                $this->translator->trans('netformic-bc-integration.customerSync.batchEmptySyncComplete')
            );

            return;
        }

        // Preload default required entities for Shopware Customers
        $groupId = $this->getDefaultCustomerGroupId($context);
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $paymentMethodId = $this->getDefaultPaymentMethodId($context);
        $salutationId = $this->getDefaultSalutationId($context);

        // Map BC customer numbers to existing Shopware customer IDs to avoid duplicates
        $customerNumbers = array_column($bcCustomers, 'no');
        $existingCustomers = $this->getExistingShopwareCustomers($customerNumbers, $context);

        // Fetch ship-to addresses for the batch
        $shipToAddresses = $this->apiClient->fetchCustomerAddresses($customerNumbers);

        $upsertData = [];

        foreach ($bcCustomers as $bcCustomer) {
            try {
                $customerPayload = $this->buildCustomerPayload(
                    $bcCustomer,
                    $existingCustomers,
                    $shipToAddresses,
                    $groupId,
                    $salesChannelId,
                    $paymentMethodId,
                    $salutationId,
                    $context
                );

                if ($customerPayload) {
                    $upsertData[] = $customerPayload;
                }
            } catch (\Throwable $e) {
                $this->logger->warning($this->translator->trans('netformic-bc-integration.customerSync.customerMappingFailed'), [
                    'bcId' => $bcCustomer['systemId'] ?? null,
                    'customerNumber' => $bcCustomer['no'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($upsertData)) {
            try {
                $this->customerRepository->upsert($upsertData, $context);
            } catch (\Throwable $e) {
                $this->logger->warning($this->translator->trans('netformic-bc-integration.customerSync.bulkUpsertFailedFallback'), [
                    'batch'           => $nextLink ?? 'initial',
                    'customerIds'     => array_column($upsertData, 'id'),
                    'customerNumbers' => array_column($upsertData, 'customerNumber'),
                    'error'           => $e->getMessage(),
                ]);

                foreach ($upsertData as $singleRow) {
                    $systemId = $singleRow['customFields'][PluginConfig::CUSTOM_FIELD_CUSTOMER_SYSTEM_ID->value] ?? null;
                    if ($systemId) {
                        $this->messageBus->dispatch(new SyncCustomerMessage($systemId));
                    } else {
                        $this->logger->error($this->translator->trans('netformic-bc-integration.customerSync.queueCustomerMissingSystemId'), [
                            'customerId'     => $singleRow['id'] ?? null,
                            'customerNumber' => $singleRow['customerNumber'] ?? 'unknown',
                        ]);
                    }
                }
            }
        }

        if ($newNextLink) {
            $this->messageBus->dispatch(
                new SyncCustomersMessage($newNextLink)
            );
        } else {
            $this->logger->info($this->translator->trans('netformic-bc-integration.customerSync.syncCompleted'));
        }
    }

    /**
     * Synchronizes a single customer by its Microsoft BC system ID.
     *
     * @param string $systemId The Microsoft BC system ID of the customer
     * @param Context|null $context The context object
     */
    public function syncCustomer(string $systemId, ?Context $context = null): void
    {
        $context ??= Context::createDefaultContext();

        $bcCustomer = $this->apiClient->fetchCustomerBySystemId($systemId);
        if (!$bcCustomer) {
            $this->logger->warning($this->translator->trans('netformic-bc-integration.customerSync.customerNotFound'), [
                'systemId' => $systemId,
            ]);

            return;
        }

        $bcNo = (string) ($bcCustomer['no'] ?? '');
        if (empty($bcNo)) {
            return;
        }

        // Preload default required entities for Shopware Customers
        $groupId = $this->getDefaultCustomerGroupId($context);
        $salesChannelId = $this->getDefaultSalesChannelId($context);
        $paymentMethodId = $this->getDefaultPaymentMethodId($context);
        $salutationId = $this->getDefaultSalutationId($context);

        $existingCustomers = $this->getExistingShopwareCustomers([$bcNo], $context);
        $shipToAddresses = $this->apiClient->fetchCustomerAddresses([$bcNo]);

        $customerPayload = $this->buildCustomerPayload(
            $bcCustomer,
            $existingCustomers,
            $shipToAddresses,
            $groupId,
            $salesChannelId,
            $paymentMethodId,
            $salutationId,
            $context
        );

        if ($customerPayload) {
            $this->customerRepository->upsert([$customerPayload], $context);
        }
    }

    /**
     * Builds the customer payload array for Shopware upsert.
     *
     * @param array $bcCustomer Customer data from Microsoft BC
     * @param array $existingCustomers Existing Shopware customer entities mapped by customer number
     * @param array $shipToAddresses Ship-to addresses from Microsoft BC
     * @param string $groupId Default customer group ID
     * @param string $salesChannelId Default sales channel ID
     * @param string $paymentMethodId Default payment method ID
     * @param string $salutationId Default salutation ID
     * @param Context $context Shopware execution context
     *
     * @return array|null The mapped customer payload, or null if validation fails
     */
    private function buildCustomerPayload(
        array $bcCustomer,
        array $existingCustomers,
        array $shipToAddresses,
        string $groupId,
        string $salesChannelId,
        string $paymentMethodId,
        string $salutationId,
        Context $context
    ): ?array {
        $bcId = $bcCustomer['systemId'] ?? $bcCustomer['id'] ?? null;

        // 1. Validate required fields
        $missingFields = [];

        if (empty(trim((string) ($bcCustomer['no'] ?? '')))) {
            $missingFields[] = 'customerNumber (no)';
        }
        $emailStr = trim((string) ($bcCustomer['eMail'] ?? ''));
        if (!empty($emailStr)) {
            // Extract the first email if multiple are provided separated by ; or ,
            $emails = preg_split('/[;,]/', $emailStr);
            $emailStr = trim($emails[0] ?? '');
        }

        if (empty($emailStr)) {
            $missingFields[] = 'email (eMail)';
        } elseif (!filter_var($emailStr, FILTER_VALIDATE_EMAIL)) {
            $missingFields[] = 'email (invalid format: ' . $emailStr . ')';
        }

        $firstName = trim((string) ($bcCustomer['name'] ?? ''));
        $lastName = "\u{200B}";

        if (empty($firstName)) {
            $missingFields[] = 'firstName (derived from name)';
        }

        $street = trim((string) ($bcCustomer['address'] ?? ''));
        $zipcode = trim((string) ($bcCustomer['postCode'] ?? ''));
        $city = trim((string) ($bcCustomer['city'] ?? ''));

        if (empty($street)) {
            $missingFields[] = 'street (address)';
        }
        if (empty($zipcode)) {
            $missingFields[] = 'zipcode (postCode)';
        }
        if (empty($city)) {
            $missingFields[] = 'city';
        }

        // If any required data is missing, skip the customer
        if (!empty($missingFields)) {
            return null;
        }

        $bcNo = (string) ($bcCustomer['no'] ?? '');

        $isNew = !isset($existingCustomers[$bcNo]);

        // Use existing ID if we have it, otherwise generate a new one
        $customerId = $isNew ? Uuid::randomHex() : $existingCustomers[$bcNo]['id'];
        $addressId = $isNew ? Uuid::randomHex() : $existingCustomers[$bcNo]['defaultBillingAddressId'];

        $email = strtolower($emailStr);
        ['id' => $countryId, 'iso' => $countryIso] = $this->getCountryIdAndIso($bcCustomer['countryRegionCode'] ?? 'US', $context);

        $countryStateId = null;
        $county = trim((string) ($bcCustomer['county'] ?? ''));
        if (!empty($county)) {
            $countryStateId = $this->getCountryStateIdByCode($countryId, $county, $context, $countryIso);
        }

        $customerPayload = [
            'id' => $customerId,
            'customerNumber' => (string) $bcCustomer['no'],
            'salesChannelId' => $salesChannelId,
            'groupId' => $groupId,
            'defaultPaymentMethodId' => $paymentMethodId,
            'salutationId' => $salutationId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'company' => $bcCustomer['name'] ?? '',
            'accountType' => 'business',
            'active' => empty(trim((string) ($bcCustomer['blocked'] ?? ''))),
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'customFields' => [
                PluginConfig::CUSTOM_FIELD_CUSTOMER_SYSTEM_ID->value => $bcId,
                PluginConfig::CUSTOM_FIELD_BALANCE->value => (float) ($bcCustomer['balance'] ?? 0),
                PluginConfig::CUSTOM_FIELD_CREDIT_AMOUNT->value => (float) ($bcCustomer['creditAmount'] ?? 0),
                PluginConfig::CUSTOM_FIELD_CONTACT->value => trim((string) ($bcCustomer['contact'] ?? '')),
            ],
            'specificFeatures' => [
                'features' => [
                    'QUICK_ORDER' => true,
                    'EMPLOYEE_MANAGEMENT' => true,
                    'ORDER_APPROVAL' => true,
                    'QUOTE_MANAGEMENT' => true,
                    'SHOPPING_LISTS' => true,
                    'ORGANIZATION_UNITS' => true,
                    'BUDGET_MANAGEMENT' => true,
                ],
            ],
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'salutationId' => $salutationId,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'company' => $bcCustomer['name'] ?? '',
                    'street' => $street,
                    'zipcode' => $zipcode,
                    'city' => $city,
                    'countryId' => $countryId,
                    'countryStateId' => $countryStateId,
                    'phoneNumber' => $bcCustomer['phoneNo'] ?? null,
                    'customFields' => [
                        PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_DOCUMENT_CODE->value => $bcCustomer['ffl'] ?? null,
                        PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_EXPIRY->value => (!empty($bcCustomer['fflExpiry']) && $bcCustomer['fflExpiry'] !== '0001-01-01' && $bcCustomer['fflExpiry'] !== '0001-01-01T00:00:00Z') ? (new \DateTime($bcCustomer['fflExpiry']))->format(\DATE_ATOM) : null,
                        PluginConfig::CUSTOM_FIELD_IS_BC_ADDRESS->value => true,
                    ],
                ]
            ]
        ];

        // Append additional ship-to addresses if any
        if (!empty($shipToAddresses[$bcNo])) {
            foreach ($shipToAddresses[$bcNo] as $shipTo) {
                // Validate required fields for the address
                $shipToStreet = trim((string) ($shipTo['address'] ?? ''));
                $shipToZip = trim((string) ($shipTo['postCode'] ?? ''));
                $shipToCity = trim((string) ($shipTo['city'] ?? ''));

                if (empty($shipToStreet) || empty($shipToZip) || empty($shipToCity)) {
                    continue;  // Skip invalid addresses silently
                }

                $shipToFirstName = trim((string) ($shipTo['name'] ?? ''));
                $shipToLastName = "\u{200B}";

                if (empty($shipToFirstName)) {
                    $shipToFirstName = $firstName;  // Fallback to main customer name
                }

                ['id' => $shipToCountryId, 'iso' => $shipToCountryIso] = $this->getCountryIdAndIso($shipTo['countryRegionCode'] ?? 'US', $context);
                $shipToCountryStateId = null;
                $shipToCounty = trim((string) ($shipTo['county'] ?? ''));
                if (!empty($shipToCounty)) {
                    $shipToCountryStateId = $this->getCountryStateIdByCode($shipToCountryId, $shipToCounty, $context, $shipToCountryIso);
                }

                // Use a deterministic ID based on BC customer number and Ship-To code
                // This prevents creating duplicate addresses on repeated syncs
                $shipToCode = trim((string) ($shipTo['code'] ?? ''));
                if (empty($shipToCode)) {
                    continue;
                }
                $shipToId = Uuid::fromStringToHex('shipto_' . $bcNo . '_' . $shipToCode);

                $customerPayload['addresses'][] = [
                    'id' => $shipToId,
                    'customerId' => $customerId,
                    'salutationId' => $salutationId,
                    'firstName' => $shipToFirstName,
                    'lastName' => $shipToLastName,
                    'company' => $shipTo['name'] ?? '',
                    'street' => $shipToStreet,
                    'zipcode' => $shipToZip,
                    'city' => $shipToCity,
                    'countryId' => $shipToCountryId,
                    'countryStateId' => $shipToCountryStateId,
                    'phoneNumber' => $shipTo['phoneNo'] ?? null,
                    'customFields' => [
                        PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_DOCUMENT_CODE->value => $shipTo['ffl'] ?? null,
                        PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_EXPIRY->value => (!empty($shipTo['fflExpiry']) && $shipTo['fflExpiry'] !== '0001-01-01' && $shipTo['fflExpiry'] !== '0001-01-01T00:00:00Z') ? (new \DateTime($shipTo['fflExpiry']))->format(\DATE_ATOM) : null,
                        PluginConfig::CUSTOM_FIELD_IS_BC_ADDRESS->value => true,
                    ],
                ];
            }
        }

        // If brand new, generate a cryptographically strong random password
        if ($isNew) {
            $customerPayload['password'] = bin2hex(random_bytes(32));  // 64-character hex string
        }

        return $customerPayload;
    }

    /**
     * Returns existing Shopware customers indexed by BC customer number.
     *
     * @param array<int, string> $customerNumbers
     * @return array<string, array<string, string|null>>
     */
    private function getExistingShopwareCustomers(array $customerNumbers, Context $context): array
    {
        if (empty($customerNumbers)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('customerNumber', $customerNumbers));

        $customers = $this->customerRepository->search($criteria, $context);

        $map = [];
        foreach ($customers as $customer) {
            $map[$customer->getCustomerNumber()] = [
                'id' => $customer->getId(),
                'defaultBillingAddressId' => $customer->getDefaultBillingAddressId()
            ];
        }

        return $map;
    }

    /**
     * Returns the default customer group used for imported customers.
     */
    private function getDefaultCustomerGroupId(Context $context): string
    {
        if ($this->defaultCustomerGroupId) {
            return $this->defaultCustomerGroupId;
        }

        $group = $this->customerGroupRepository->search(new Criteria(), $context)->first();
        if (!$group) {
            throw new \RuntimeException('No Customer Group found in Shopware.');
        }

        return $this->defaultCustomerGroupId = $group->getId();
    }

    /**
     * Returns a storefront sales channel for imported customers.
     */
    private function getDefaultSalesChannelId(Context $context): string
    {
        if ($this->defaultSalesChannelId) {
            return $this->defaultSalesChannelId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));
        $channel = $this->salesChannelRepository->search($criteria, $context)->first();

        if (!$channel) {
            $channel = $this->salesChannelRepository->search(new Criteria(), $context)->first();
        }

        if (!$channel) {
            throw new \RuntimeException('No Sales Channel found in Shopware.');
        }

        return $this->defaultSalesChannelId = $channel->getId();
    }

    /**
     * Returns the default active payment method.
     */
    private function getDefaultPaymentMethodId(Context $context): string
    {
        if ($this->defaultPaymentMethodId) {
            return $this->defaultPaymentMethodId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $paymentMethod = $this->paymentMethodRepository->search($criteria, $context)->first();

        if (!$paymentMethod) {
            throw new \RuntimeException('No active Payment Method found in Shopware.');
        }

        return $this->defaultPaymentMethodId = $paymentMethod->getId();
    }

    /**
     * Returns the default salutation used for imported customers.
     */
    private function getDefaultSalutationId(Context $context): string
    {
        if ($this->defaultSalutationId) {
            return $this->defaultSalutationId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('salutationKey', 'not_specified'));
        $salutation = $this->salutationRepository->search($criteria, $context)->first();

        if (!$salutation) {
            $salutation = $this->salutationRepository->search(new Criteria(), $context)->first();
        }

        if (!$salutation) {
            throw new \RuntimeException('No Salutation found in Shopware.');
        }

        return $this->defaultSalutationId = $salutation->getId();
    }

    /**
     * Resolves a country ID and ISO from an ISO code and caches the result.
     *
     * @return array{id: string, iso: string}
     */
    private function getCountryIdAndIso(string $iso, Context $context): array
    {
        $iso = trim($iso);
        if ($iso === '') {
            $iso = 'US';
        }

        $iso = strtoupper($iso);
        if (isset($this->countryCache[$iso])) {
            return $this->countryCache[$iso];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('iso', $iso));
        $country = $this->countryRepository->search($criteria, $context)->first();

        if (!$country) {
            $country = $this->countryRepository->search(new Criteria(), $context)->first();
        }

        if (!$country) {
            throw new \RuntimeException('No Country found in Shopware.');
        }

        return $this->countryCache[$iso] = [
            'id'  => $country->getId(),
            'iso' => strtoupper((string) $country->getIso()),
        ];
    }

    /**
     * Resolves a country state ID from a state code (county) and caches the result.
     */
    private function getCountryStateIdByCode(string $countryId, string $stateCode, Context $context): ?string
    {
        $stateCode = strtoupper(trim($stateCode));
        $cacheKey = $countryId . '_' . $stateCode;

        if (array_key_exists($cacheKey, $this->countryStateIdCache)) {
            return $this->countryStateIdCache[$cacheKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('countryId', $countryId));

        $possibleShortCodes = [$stateCode];
        if (!empty($countryIso)) {
            $countryIso = strtoupper(trim($countryIso));
            if (!str_contains($stateCode, '-')) {
                $possibleShortCodes[] = $countryIso . '-' . $stateCode;
            }
        }

        // In Shopware, shortCode is often prefixed with the ISO (e.g. US-CA, CA-ON)
        // or just matches the name/shortCode directly. We will search both.
        $criteria->addFilter(new EqualsAnyFilter('shortCode', array_unique($possibleShortCodes)));

        $state = $this->countryStateRepository->search($criteria, $context)->first();

        // If not found by short code, try to match by name
        if (!$state) {
            $nameCriteria = new Criteria();
            $nameCriteria->addFilter(new EqualsFilter('countryId', $countryId));
            $nameCriteria->addFilter(new EqualsFilter('name', $stateCode));
            $state = $this->countryStateRepository->search($nameCriteria, $context)->first();
        }

        return $this->countryStateIdCache[$cacheKey] = $state ? $state->getId() : null;
    }
}
