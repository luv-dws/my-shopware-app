<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncInvoicesMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service for synchronizing sales invoices from Microsoft Business Central to Shopware
 */
class InvoiceSyncService
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BcApiClient $bcApiClient,
        private readonly EntityRepository $salesInvoiceRepository,
        private readonly EntityRepository $orderRepository,
        private readonly EntityRepository $productRepository,
        private readonly EntityRepository $customerRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * Executes the synchronization process for invoices.
     *
     * @param string|null $nextLink Optional URL for fetching the next page of results
     * @param int $batchSize Number of records to fetch per request
     * @param string|null $lastSyncDate Optional date string to filter by last modified
     *
     * @throws \Throwable
     */
    public function sync(?string $nextLink = null, int $batchSize = 50, ?string $lastSyncDate = null): void
    {
        $context = Context::createDefaultContext();

        try {
            $response = $this->bcApiClient->fetchInvoices($nextLink, $lastSyncDate, $batchSize);
            $invoices = $response['value'] ?? [];
            $newNextLink = $response['@odata.nextLink'] ?? null;
            $requestUrl = $response['_request_url'] ?? 'unknown_url';

            if (empty($invoices)) {
                $this->logger->info($this->translator->trans('netformic-bc-integration.invoiceSync.noInvoicesFound'), ['url' => $requestUrl]);
            } else {
                $productNumberMap = $this->buildProductNumberMap($invoices, $context);
                $customerNumberMap = $this->buildCustomerNumberMap($invoices, $context);

                $upsertPayload = $this->buildUpsertPayload($invoices, $productNumberMap, $customerNumberMap, $context);

                if (!empty($upsertPayload)) {
                    $this->logger->info($this->translator->trans('netformic-bc-integration.invoiceSync.upsertingInvoices', ['%count%' => count($upsertPayload)]), [
                        'api_url' => $requestUrl,
                    ]);
                    $this->salesInvoiceRepository->upsert($upsertPayload, $context);
                    $this->logger->info($this->translator->trans('netformic-bc-integration.invoiceSync.upsertSuccess', ['%count%' => count($upsertPayload)]));
                }
            }

            if ($newNextLink) {
                // Dispatch message for next page
                $this->messageBus->dispatch(new SyncInvoicesMessage($newNextLink, $batchSize, $lastSyncDate));
            } elseif ($nextLink === null || empty($newNextLink)) {
                $this->logger->info($this->translator->trans('netformic-bc-integration.invoiceSync.syncCompleted'));
            }

        } catch (\Throwable $e) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.invoiceSync.syncFailed'), [
                'exception' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Builds a map of BC item numbers to Shopware product IDs.
     *
     * @param array<int, array<string, mixed>> $invoices
     * @param Context $context
     *
     * @return array<string, string>
     */
    private function buildProductNumberMap(array $invoices, Context $context): array
    {
        $productNumbers = [];
        foreach ($invoices as $invoice) {
            if (!empty($invoice['salesInvoiceLines'])) {
                foreach ($invoice['salesInvoiceLines'] as $line) {
                    if (!empty($line['lineObjectNumber'])) {
                        $productNumbers[] = $line['lineObjectNumber'];
                    }
                }
            }
        }

        if (empty($productNumbers)) {
            return [];
        }

        $productNumberMap = [];
        $productNumbers = array_unique($productNumbers);
        $productCriteria = new Criteria();
        $productCriteria->addFilter(new EqualsAnyFilter('productNumber', $productNumbers));
        
        $products = $this->productRepository->search($productCriteria, $context)->getEntities();
        foreach ($products as $product) {
            $productNumberMap[$product->getProductNumber()] = $product->getId();
        }

        return $productNumberMap;
    }

    /**
     * Builds a map of BC customer numbers to Shopware customer IDs.
     *
     * @param array<int, array<string, mixed>> $invoices
     * @param Context $context
     *
     * @return array<string, string>
     */
    private function buildCustomerNumberMap(array $invoices, Context $context): array
    {
        $customerNumbers = [];
        foreach ($invoices as $invoice) {
            if (!empty($invoice['customerNumber'])) {
                $customerNumbers[] = $invoice['customerNumber'];
            }
        }

        if (empty($customerNumbers)) {
            return [];
        }

        $customerNumberMap = [];
        $customerNumbers = array_unique($customerNumbers);
        $customerCriteria = new Criteria();
        $customerCriteria->addFilter(new EqualsAnyFilter('customerNumber', $customerNumbers));
        
        $customers = $this->customerRepository->search($customerCriteria, $context)->getEntities();
        foreach ($customers as $customer) {
            $customerNumberMap[$customer->getCustomerNumber()] = $customer->getId();
        }

        return $customerNumberMap;
    }

    /**
     * Builds the DAL upsert payload for a batch of invoices.
     *
     * @param array<int, array<string, mixed>> $invoices
     * @param array<string, string> $productNumberMap
     * @param array<string, string> $customerNumberMap
     * @param Context $context
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildUpsertPayload(array $invoices, array $productNumberMap, array $customerNumberMap, Context $context): array
    {
        $upsertPayload = [];
        foreach ($invoices as $invoice) {
            // Try to find matching shopware order
            $orderId = null;
            if (!empty($invoice['orderNumber'])) {
                $orderCriteria = new Criteria();
                $orderCriteria->addFilter(new EqualsFilter('orderNumber', $invoice['orderNumber']));
                $orderId = $this->orderRepository->searchIds($orderCriteria, $context)->firstId();
            }

            $customerId = null;
            if (!empty($invoice['customerNumber'])) {
                $customerId = $customerNumberMap[$invoice['customerNumber']] ?? null;
            }

            // Build invoice entity
            $invoiceData = $this->mapInvoiceData($invoice, $customerId, $orderId);

            // Handle lines if present
            if (!empty($invoice['salesInvoiceLines'])) {
                $invoiceData['lineItems'] = $this->mapInvoiceLines($invoice['salesInvoiceLines'], $productNumberMap);
            }

            // We need to upsert by systemId. 
            // Let's find if it exists to get the ID.
            $invoiceCriteria = new Criteria();
            $invoiceCriteria->addFilter(new EqualsFilter('systemId', $invoice['id']));
            $existingInvoiceId = $this->salesInvoiceRepository->searchIds($invoiceCriteria, $context)->firstId();

            $invoiceData['id'] = $existingInvoiceId ?: Uuid::randomHex();

            $upsertPayload[] = $invoiceData;
        }

        return $upsertPayload;
    }

    /**
     * Maps a single BC invoice record into the Shopware DAL structure.
     *
     * @param array<string, mixed> $invoice
     * @param string|null $customerId
     * @param string|null $orderId
     *
     * @return array<string, mixed>
     */
    private function mapInvoiceData(array $invoice, ?string $customerId, ?string $orderId): array
    {
        $invoiceData = [
            'systemId' => $invoice['id'],
            'number' => $invoice['number'] ?? null,
            'externalDocumentNumber' => $invoice['externalDocumentNumber'] ?? null,
            'customerId' => $customerId,
            'invoiceDate' => !empty($invoice['invoiceDate']) ? (new \DateTimeImmutable($invoice['invoiceDate']))->format('Y-m-d') : null,
            'dueDate' => !empty($invoice['dueDate']) ? (new \DateTimeImmutable($invoice['dueDate']))->format('Y-m-d') : null,
            'currencyCode' => $invoice['currencyCode'] ?? null,
            'totalAmountIncludingTax' => isset($invoice['totalAmountIncludingTax']) ? (float)$invoice['totalAmountIncludingTax'] : null,
            'discountAmount' => isset($invoice['discountAmount']) ? (float)$invoice['discountAmount'] : null,
            'remainingAmount' => isset($invoice['remainingAmount']) ? (float)$invoice['remainingAmount'] : null,
            'salesperson' => $invoice['salesperson'] ?? null,
            'status' => $invoice['status'] ?? null,
            'paymentTermsId' => $invoice['paymentTermsId'] ?? null,
            'shipmentMethodId' => $invoice['shipmentMethodId'] ?? null,

            // Bill-to Address
            'billToName' => $invoice['billToName'] ?? null,
            'billToAddressLine1' => $invoice['billToAddressLine1'] ?? null,
            'billToAddressLine2' => $invoice['billToAddressLine2'] ?? null,
            'billToCity' => $invoice['billToCity'] ?? null,
            'billToCountry' => $invoice['billToCountry'] ?? null,
            'billToState' => $invoice['billToState'] ?? null,
            'billToPostCode' => $invoice['billToPostCode'] ?? null,

            // Ship-to Address
            'shipToName' => $invoice['shipToName'] ?? null,
            'shipToContact' => $invoice['shipToContact'] ?? null,
            'shipToAddressLine1' => $invoice['shipToAddressLine1'] ?? null,
            'shipToAddressLine2' => $invoice['shipToAddressLine2'] ?? null,
            'shipToCity' => $invoice['shipToCity'] ?? null,
            'shipToCountry' => $invoice['shipToCountry'] ?? null,
            'shipToState' => $invoice['shipToState'] ?? null,
            'shipToPostCode' => $invoice['shipToPostCode'] ?? null,

            // Sell-to Address
            'sellToAddressLine1' => $invoice['sellToAddressLine1'] ?? null,
            'sellToAddressLine2' => $invoice['sellToAddressLine2'] ?? null,
            'sellToCity' => $invoice['sellToCity'] ?? null,
            'sellToCountry' => $invoice['sellToCountry'] ?? null,
            'sellToState' => $invoice['sellToState'] ?? null,
            'sellToPostCode' => $invoice['sellToPostCode'] ?? null,
        ];

        if ($orderId) {
            $invoiceData['orderId'] = $orderId;
        }

        return $invoiceData;
    }

    /**
     * Maps BC invoice lines to Shopware line items.
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, string> $productNumberMap
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapInvoiceLines(array $lines, array $productNumberMap): array
    {
        $lineItems = [];
        foreach ($lines as $line) {
            $lineItemNumber = $line['lineObjectNumber'] ?? null;
            $productId = $lineItemNumber ? ($productNumberMap[$lineItemNumber] ?? null) : null;
            
            $lineItems[] = [
                'id' => Uuid::fromStringToHex('invoice_line_' . $line['id']),
                'systemId' => $line['id'],
                'itemNumber' => $lineItemNumber,
                'productId' => $productId,
                'description' => $line['description'] ?? null,
                'unitPrice' => isset($line['unitPrice']) ? (float)$line['unitPrice'] : null,
                'quantity' => isset($line['quantity']) ? (float)$line['quantity'] : null,
                'unitOfMeasureCode' => $line['unitOfMeasureCode'] ?? null,
                'netAmountIncludingTax' => isset($line['netAmountIncludingTax']) ? (float)$line['netAmountIncludingTax'] : null,
                'shipmentDate' => !empty($line['shipmentDate']) ? (new \DateTimeImmutable($line['shipmentDate']))->format('Y-m-d') : null,
            ];
        }

        return $lineItems;
    }
}
