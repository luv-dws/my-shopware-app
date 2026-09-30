<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Handles communication with the Microsoft Business Central API.
 */
class BcApiClient
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private HttpClientInterface $client,
        private SystemConfigService $systemConfigService,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Fetches all categories from Microsoft Business Central.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchCategories(): array
    {
        // Load credentials from the currently selected API mode
        // (sandbox or production)
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        // Ensure all required configuration values exist
        if (!$apiUrl || !$username || !$password || !$companyId) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured'), [
                'mode' => $mode,
                'hasApiUrl' => (bool) $apiUrl,
                'hasUsername' => (bool) $username,
                'hasPassword' => (bool) $password,
                'hasCompanyId' => (bool) $companyId,
            ]);

            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        $endpoint = sprintf(
            '%s/companies(%s)/itemCategoriesOrionMFG?%%24expand=itemCategoriesSubOrionMFG',
            $apiUrl,
            $companyId
        );

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingCategories'), [
            'endpoint' => $endpoint,
            'companyId' => $companyId,
            'mode' => $mode,
        ]);

        try {
            // Execute API request using cURL
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            // Convert JSON response to PHP array
            $data = json_decode($responseBody, true);

            // Handle API errors
            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCategoriesFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailed', [
                        '%statusCode%' => $statusCode,
                    ])
                );
            }

            $categories = $data['value'] ?? [];

            $this->logger->info(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCategoriesSuccess', [
                    '%count%' => count($categories),
                ])
            );

            return $categories;
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCategoriesException'),
                [
                    'endpoint' => $endpoint,
                    'exception' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches a batch of products from Microsoft Business Central using OData pagination.
     *
     * @param string|null $nextLink The URL to the next page of results, if any
     * @param int $take Number of records to retrieve per page
     *
     * @return array{data: array<int, array<string, mixed>>, nextLink: string|null}
     */
    public function fetchProducts(?string $nextLink = null, int $take = 50): array
    {
        // Load credentials from the currently selected API mode
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        // Ensure all required configuration values exist
        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        // Product endpoint with nextLink pagination support
        if ($nextLink) {
            $endpoint = $nextLink;
        } else {
            $endpoint = sprintf(
                "%s/companies(%s)/itemsOrionMFG?\$expand=salesPricesOrionMFG",
                $apiUrl,
                $companyId
            );
        }

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingProducts'), [
            'endpoint' => $endpoint,
            'take' => $take,
        ]);

        try {
            // Execute API request using cURL
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Prefer: odata.maxpagesize=' . $take,
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            // Convert JSON response to PHP array
            $data = json_decode($responseBody, true);

            // Handle API errors
            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchProductsFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailed', [
                        '%statusCode%' => $statusCode,
                    ])
                );
            }

            // Return the product collection and nextLink
            return [
                'data' => $data['value'] ?? [],
                'nextLink' => $data['@odata.nextLink'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchProductsException'),
                [
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches a batch of customers from Microsoft Business Central using OData pagination.
     *
     * @param string|null $nextLink The URL to the next page of results, if any
     * @param int $take Number of records to retrieve per page
     *
     * @return array{data: array<int, array<string, mixed>>, nextLink: string|null}
     */
    public function fetchCustomers(?string $nextLink = null, int $take = 50): array
    {
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        if ($nextLink) {
            $endpoint = $nextLink;
        } else {
            $selectParams = implode(',', [
                'id',
                'no',
                'eMail',
                'contact',
                'address',
                'postCode',
                'city',
                'county',
                'name',
                'blocked',
                'countryRegionCode',
                'balance',
                'balanceDue',
                'creditAmount',
                'crMemoAmounts',
                'debitAmount',
                'phoneNo',
                'systemId',
                'ffl',
                'fflExpiry'
            ]);

            $filterParams = "eMail ne '' and contact ne ''";

            $endpoint = sprintf(
                '%s/companies(%s)/customersOrionMFG?$select=%s&$filter=%s',
                $apiUrl,
                $companyId,
                $selectParams,
                urlencode($filterParams)
            );
        }

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingCustomers'), [
            'endpoint' => $endpoint,
            'take' => $take,
        ]);

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Prefer: odata.maxpagesize=' . $take,
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            $data = json_decode($responseBody, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCustomersFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailed', [
                        '%statusCode%' => $statusCode,
                    ])
                );
            }

            return [
                'data' => $data['value'] ?? [],
                'nextLink' => $data['@odata.nextLink'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCustomersException'),
                [
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches one customer from Microsoft Business Central by system ID.
     *
     * @return array<string, mixed>|null
     */
    public function fetchCustomerBySystemId(string $systemId): ?array
    {
        $systemId = trim($systemId);
        if (empty($systemId)) {
            return null;
        }

        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        $selectParams = implode(',', [
            'id',
            'no',
            'eMail',
            'contact',
            'address',
            'postCode',
            'city',
            'county',
            'name',
            'blocked',
            'countryRegionCode',
            'balance',
            'balanceDue',
            'creditAmount',
            'crMemoAmounts',
            'debitAmount',
            'phoneNo',
            'systemId',
            'ffl',
            'fflExpiry'
        ]);

        $escapedSystemId = str_replace("'", "''", $systemId);
        $endpoint = sprintf(
            "%s/companies(%s)/customersOrionMFG?\$select=%s&\$filter=%s&\$top=1",
            $apiUrl,
            $companyId,
            $selectParams,
            urlencode("systemId eq {$escapedSystemId}")
        );

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingCustomerBySystemId'), [
            'endpoint' => $endpoint,
            'systemId' => $systemId,
        ]);

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            $data = json_decode($responseBody, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCustomerBySystemIdFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailed', [
                        '%statusCode%' => $statusCode,
                    ])
                );
            }

            return $data['value'][0] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCustomerBySystemIdException'),
                [
                    'systemId' => $systemId,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches one product from Microsoft Business Central by system ID.
     *
     * @return array<string, mixed>|null
     */
    public function fetchProductBySystemId(string $systemId): ?array
    {
        $systemId = trim($systemId);
        if (empty($systemId)) {
            return null;
        }

        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        $escapedSystemId = str_replace("'", "''", $systemId);
        $endpoint = sprintf(
            "%s/companies(%s)/itemsOrionMFG?\$expand=salesPricesOrionMFG&\$filter=%s&\$top=1",
            $apiUrl,
            $companyId,
            urlencode("systemId eq {$escapedSystemId}")
        );

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingProductBySystemId'), [
            'endpoint' => $endpoint,
            'systemId' => $systemId,
        ]);

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            $data = json_decode($responseBody, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchProductBySystemIdFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailed', [
                        '%statusCode%' => $statusCode,
                    ])
                );
            }

            return $data['value'][0] ?? null;
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchProductBySystemIdException'),
                [
                    'systemId' => $systemId,
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches ship-to addresses for a batch of customer numbers.
     *
     * @param array<int, string> $customerNumbers
     * @return array<string, array<int, array<string, mixed>>> Array of addresses grouped by customer number
     */
    public function fetchCustomerAddresses(array $customerNumbers): array
    {
        if (empty($customerNumbers)) {
            return [];
        }

        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        // Build filter for customer numbers
        $filterConditions = array_map(function ($no) {
            return sprintf("customerNo eq '%s'", $no);
        }, $customerNumbers);
        
        $filter = implode(' or ', $filterConditions);

        $endpoint = sprintf(
            '%s/companies(%s)/shiptoAddressesOrionMFG?$filter=%s',
            $apiUrl,
            $companyId,
            urlencode($filter)
        );

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            $data = json_decode($responseBody, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCustomerAddressesFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                    ]
                );
                return []; // Do not fail the whole sync if addresses fail
            }

            $addresses = $data['value'] ?? [];
            
            // Group addresses by customer number
            $groupedAddresses = [];
            foreach ($addresses as $address) {
                $customerNo = $address['customerNo'] ?? '';
                if ($customerNo) {
                    $groupedAddresses[$customerNo][] = $address;
                }
            }

            return $groupedAddresses;
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchCustomerAddressesException'),
                [
                    'exception' => $e->getMessage(),
                ]
            );

            return []; // Do not fail the whole sync if addresses fail
        }
    }

    /**
     * Pushes a new order to Microsoft Business Central.
     *
     * @param array<string, mixed> $orderData The order payload
     * @return array<string, mixed> The response from BC
     */
    public function pushOrder(array $orderData): array
    {
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        // Use custom ApiUrl for salesOrdersOrionMFG
        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        $endpoint = sprintf(
            '%s/companies(%s)/salesOrdersOrionMFG',
            $apiUrl,
            $companyId
        );

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.pushingOrder'), [
            'endpoint' => $endpoint,
            'customerNumber' => $orderData['customerNumber'] ?? 'Unknown'
        ]);

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            $data = json_decode($responseBody, true) ?: [];

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorCode = $data['error']['code'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.unknownError');
                $errorMessage = $data['error']['message'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.noAdditionalErrorMessage');

                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.pushOrderFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                        'payload' => $orderData,
                        'errorCode' => $errorCode,
                        'errorMessage' => $errorMessage
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailedWithDetails', [
                        '%statusCode%' => $statusCode,
                        '%errorCode%' => $errorCode,
                        '%errorMessage%' => $errorMessage,
                    ])
                );
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.pushOrderException'),
                [
                    'exception' => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches orders from Microsoft Business Central OrionMFG custom endpoint.
     *
     * @param string|null $nextLink
     * @param int $take
     * @param string|null $lastSyncDate
     * @return array{data: array<int, array<string, mixed>>, nextLink: string|null}
     */
    public function fetchOrders(?string $nextLink = null, int $take = 50, ?string $lastSyncDate = null): array
    {
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');

        if ($nextLink) {
            $endpoint = $nextLink;
        } else {
            $endpoint = sprintf(
                '%s/companies(%s)/salesOrdersOrionMFG?$select=systemId,number,status',
                $apiUrl,
                $companyId
            );
            if ($lastSyncDate) {
                // Check if endpoint already has query parameters (it does specify select)
                $endpoint .= '&$filter=lastModifiedDateTime%20ge%20' . urlencode($lastSyncDate);
            }
        }

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingOrders'), [
            'endpoint' => $endpoint,
            'take' => $take,
        ]);

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Prefer: odata.maxpagesize=' . $take,
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);

            curl_close($ch);

            if ($curlError) {
                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
                );
            }

            $data = json_decode($responseBody, true);

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorCode = $data['error']['code'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.unknownError');
                $errorMessage = $data['error']['message'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.noAdditionalErrorMessage');

                $this->logger->error(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.fetchOrdersFailed'),
                    [
                        'endpoint' => $endpoint,
                        'statusCode' => $statusCode,
                        'response' => $data,
                        'errorCode' => $errorCode,
                        'errorMessage' => $errorMessage
                    ]
                );

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailedWithDetails', [
                        '%statusCode%' => $statusCode,
                        '%errorCode%' => $errorCode,
                        '%errorMessage%' => $errorMessage,
                    ])
                );
            }

            return [
                'data' => $data['value'] ?? [],
                'nextLink' => $data['@odata.nextLink'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchOrdersException'),
                [
                    'exception' => $e->getMessage(),
                    'endpoint' => $endpoint ?? null,
                ]
            );

            throw $e;
        }
    }

    /**
     * Fetches sales invoices from Business Central, optionally filtering by date.
     *
     * @param string|null $nextLink The URL for the next page of results (pagination).
     * @param string|null $lastSyncDate ISO-8601 formatted date to filter invoices modified after this time.
     * @return array<string, mixed> The response containing 'value' and optionally '@odata.nextLink'.
     *
     * @throws \RuntimeException If the request fails or is missing configuration.
     */
    public function fetchInvoices(?string $nextLink = null, ?string $lastSyncDate = null, int $take = 50): array
    {
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;
        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}ApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        if ($nextLink) {
            $url = $nextLink;
        } else {
            $apiUrl = rtrim($apiUrl, '/');
            $filter = '';
            
            if ($lastSyncDate) {
                // e.g., $filter=lastModifiedDateTime ge 2026-05-06T00:00:00Z
                $filter = '&$filter=lastModifiedDateTime%20ge%20' . $lastSyncDate;
            }
            
            $url = sprintf(
                '%s/companies(%s)/salesInvoices?$expand=salesInvoiceLines%s',
                $apiUrl,
                $companyId,
                $filter
            );
        }

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.fetchingSalesInvoices'), [
            'url' => $url,
            'take' => $take,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 60 seconds timeout
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Prefer: odata.maxpagesize=' . $take,
        ]);

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
            );
        }

        $data = json_decode((string)$responseBody, true) ?: [];

        if ($statusCode < 200 || $statusCode >= 300) {
            $errorCode = $data['error']['code'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.unknownError');
            $errorMessage = $data['error']['message'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.noAdditionalErrorMessage');

            $this->logger->error(
                $this->translator->trans('netformic-bc-integration.bcApiClient.fetchInvoicesFailed'),
                [
                    'status_code' => $statusCode,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'url' => $url
                ]
            );

            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailedWithDetails', [
                    '%statusCode%' => $statusCode,
                    '%errorCode%' => $errorCode,
                    '%errorMessage%' => $errorMessage,
                ])
            );
        }

        $data['_request_url'] = $url;
        
        return $data;
    }
        
    /* Sends a generic request to the Microsoft Business Central API.
     *
     * @param string $method The HTTP method (GET, POST, etc.)
     * @param string $endpoint The endpoint path (e.g., 'salesOrders')
     * @return array<string, mixed> The response data
     */
    public function request(string $method, string $endpoint): array
    {
        $mode = $this->getApiMode();

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        // Use standard ApiUrl
        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}ApiUrl");
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfigured')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');
        
        // Handle full URLs (like pagination nextLinks) or relative endpoints
        if (str_starts_with($endpoint, 'http')) {
            $fullUrl = $endpoint;
        } else {
            $fullUrl = sprintf(
                '%s/companies(%s)/%s',
                $apiUrl,
                $companyId,
                ltrim($endpoint, '/')
            );
        }

        $this->logger->info($this->translator->trans('netformic-bc-integration.bcApiClient.makingGenericRequest'), [
            'method' => $method,
            'url' => $fullUrl
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        
        $headers = ['Accept: application/json'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $responseBody = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        if ($curlError) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.curlError', ['%error%' => $curlError])
            );
        }

        $data = json_decode((string)$responseBody, true) ?: [];

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.apiRequestFailedWithResponse', [
                    '%statusCode%' => $statusCode,
                    '%response%' => json_encode($data),
                ])
            );
        }

        return $data;
    }

    /**
     * Sends a PayFabric transaction link request to Microsoft Business Central.
     * Endpoint: {apiUrl}/Nodus/PayFabric/v5.0/companies({companyId})/PFTransactions
     *
     * @param array<string, mixed> $transactionData
     *
     * @return array<string, mixed>
     */
    public function pushPayFabricTransaction(array $transactionData): array
    {
        $mode = $this->getApiMode();
        $domain = PluginConfig::CONFIG_DOMAIN->value;

        // Use custom or standard ApiUrl configured for BC
        $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl");
        if (empty($apiUrl)) {
            $apiUrl = $this->systemConfigService->getString("{$domain}{$mode}ApiUrl");
        }
        $username = $this->systemConfigService->getString("{$domain}{$mode}Username");
        $password = $this->systemConfigService->getString("{$domain}{$mode}Password");
        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        if (!$apiUrl || !$username || !$password || !$companyId) {
            throw new \RuntimeException(
                $this->translator->trans('netformic-bc-integration.bcApiClient.notFullyConfiguredPayFabric')
            );
        }

        $apiUrl = rtrim($apiUrl, '/');
        $endpoint = sprintf(
            '%s/api/Nodus/PayFabric/v5.0/companies(%s)/PFTransactions',
            $apiUrl,
            $companyId
        );

        try {
            $response = $this->client->request('POST', $endpoint, [
                'auth_basic' => [$username, $password],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $transactionData,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);
            $data = json_decode($content, true) ?: [];

            if ($statusCode < 200 || $statusCode >= 300) {
                $errorCode = $data['error']['code'] ?? $this->translator->trans('netformic-bc-integration.bcApiClient.unknownError');
                $errorMessage = $data['error']['message'] ?? ($content ?: $this->translator->trans('netformic-bc-integration.bcApiClient.pushPayFabricFailed'));

                $this->logger->error($this->translator->trans('netformic-bc-integration.bcApiClient.pushPayFabricFailed'), [
                    'endpoint' => $endpoint,
                    'statusCode' => $statusCode,
                    'response' => $data,
                    'payload' => $transactionData,
                    'errorCode' => $errorCode,
                    'errorMessage' => $errorMessage,
                ]);

                throw new \RuntimeException(
                    $this->translator->trans('netformic-bc-integration.bcApiClient.payFabricTransactionError', [
                        '%errorCode%' => $errorCode,
                        '%errorMessage%' => $errorMessage,
                    ])
                );
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.bcApiClient.pushPayFabricException'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Resolves the current API mode ('sandbox' when switch is enabled, 'production' when disabled).
     */
    private function getApiMode(): string
    {
        return $this->systemConfigService->getBool(PluginConfig::CONFIG_CURRENT_API_MODE->value) ? 'sandbox' : 'production';
    }
}