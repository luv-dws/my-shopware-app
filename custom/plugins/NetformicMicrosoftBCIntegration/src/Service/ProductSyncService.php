<?php declare (strict_types = 1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductsMessage;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * Synchronizes products from Microsoft Business Central into Shopware.
 *
 * Products are processed in batches and additional batches are queued
 * automatically until all products have been imported.
 */
class ProductSyncService
{


    /**
     * @var array<string, string>
     */
    private array $categoryCache     = [];

    private ?string $defaultTaxId    = null;

    /**
     * @var array<int, string>|null
     */
    private ?array $salesChannelIds  = null;

    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private BcApiClient $apiClient,
        private EntityRepository $productRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private EntityRepository $taxRepository,
        private EntityRepository $categoryRepository,
        private EntityRepository $salesChannelRepository,
        private SystemConfigService $systemConfigService,
        private TranslatorInterface $translator
    ) {}

    /**
     * Processes a single batch of products from Microsoft BC.
     */
    public function syncProducts(?string $nextLink): void
    {
        $context = Context::createDefaultContext();
        $take = $this->systemConfigService->getInt(PluginConfig::CONFIG_PRODUCT_SYNC_BATCH_SIZE->value) ?: 50;

        // Fetch a batch of products from BC
        $response    = $this->apiClient->fetchProducts($nextLink, $take);
        $bcProducts  = $response['data'] ?? [];
        $newNextLink = $response['nextLink'] ?? null;

        if (empty($bcProducts)) {
            $this->logger->info($this->translator->trans('netformic-bc-integration.productSync.syncCompleted'));

            return;
        }

        $taxId           = $this->getDefaultTaxId($context);
        $salesChannelIds = $this->getAllSalesChannelIds($context);

        // Fetch existing products to avoid overwrite duplicates and get IDs
        $productNumbers = array_map(function ($p) {return (string) ($p['number'] ?? $p['no'] ?? '');}, $bcProducts);
        $productNumbers     = array_filter($productNumbers);
        $existingProductIds = $this->getExistingProductIds($productNumbers, $context);

        $upsertData = [];
        $deleteData = [];

        foreach ($bcProducts as $bcProduct) {
            try {
                $productNumber    = trim((string) ($bcProduct['number'] ?? $bcProduct['no'] ?? ''));
                $itemCategoryCode = $bcProduct['itemCategoryCode'] ?? null;

                if (is_string($itemCategoryCode) && strtoupper(trim($itemCategoryCode)) === 'DISCONTINUED') {
                    if (isset($existingProductIds[$productNumber])) {
                        $deleteData[] = ['id' => $existingProductIds[$productNumber]];
                    }
                    continue;
                }

                $productData = $this->buildProductData(
                    $bcProduct,
                    $existingProductIds,
                    $taxId,
                    $salesChannelIds,
                    $context
                );

                if ($productData) {
                    $upsertData[] = $productData;
                }
            } catch (\Throwable $e) {
                $this->logger->warning($this->translator->trans('netformic-bc-integration.productSync.productMappingFailed'), [
                    'bcId'  => $bcProduct['systemId'] ?? $bcProduct['id'] ?? null,
                    'productNumber' => $bcProduct['number'] ?? $bcProduct['no'] ?? '',
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (! empty($upsertData)) {
            try {
                $this->productRepository->upsert($upsertData, $context);
            } catch (\Throwable $e) {
                $this->logger->warning($this->translator->trans('netformic-bc-integration.productSync.bulkUpsertFailedFallback'), [
                    'batch'          => $nextLink ?? 'initial',
                    'productIds'     => array_column($upsertData, 'id'),
                    'productNumbers' => array_column($upsertData, 'productNumber'),
                    'error'          => $e->getMessage(),
                ]);

                foreach ($upsertData as $productData) {
                    $systemId = $productData['customFields'][PluginConfig::CUSTOM_FIELD_PRODUCT_SYSTEM_ID->value] ?? null;
                    if ($systemId) {
                        $this->messageBus->dispatch(new SyncProductMessage($systemId));
                    } else {
                        $this->logger->error($this->translator->trans('netformic-bc-integration.productSync.queueProductMissingSystemId'), [
                            'productNumber' => $productData['productNumber'] ?? 'unknown',
                        ]);
                    }
                }
            }
        }

        if (! empty($deleteData)) {
            try {
                $this->productRepository->delete($deleteData, $context);
                $this->logger->info($this->translator->trans('netformic-bc-integration.productSync.deleteDiscontinuedSuccess'), [
                    'count'      => count($deleteData),
                    'productIds' => array_column($deleteData, 'id'),
                ]);
            } catch (\Throwable $e) {
                $this->logger->error($this->translator->trans('netformic-bc-integration.productSync.deleteDiscontinuedFailed'), [
                    'productIds' => array_column($deleteData, 'id'),
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if ($newNextLink) {
            $this->messageBus->dispatch(
                new SyncProductsMessage($newNextLink)
            );
        } else {
            $this->logger->info($this->translator->trans('netformic-bc-integration.productSync.syncCompleted'));
        }
    }

    /**
     * @param array<int, string> $productNumbers
     *
     * @return array<string, string>
     */
    private function getExistingProductIds(array $productNumbers, Context $context): array
    {
        if (empty($productNumbers)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('productNumber', $productNumbers));

        $products = $this->productRepository->search($criteria, $context);
        $map      = [];
        foreach ($products as $product) {
            $map[$product->getProductNumber()] = $product->getId();
        }
        return $map;
    }



    /**
     * Executes the get category id by system id operation.
     *
     * @internal
     */
    private function getCategoryIdBySystemId(string $systemId, Context $context): ?string
    {
        $cacheKey = strtolower(trim($systemId));
        if (isset($this->categoryCache[$cacheKey])) {
            return $this->categoryCache[$cacheKey];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFields.' . PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value, $systemId));
        $category = $this->categoryRepository->search($criteria, $context)->first();

        if ($category) {
            $this->categoryCache[$cacheKey] = $category->getId();
            return $category->getId();
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getAllSalesChannelIds(Context $context): array
    {
        if ($this->salesChannelIds !== null) {
            return $this->salesChannelIds;
        }

        $criteria              = new Criteria();
        $this->salesChannelIds = $this->salesChannelRepository->searchIds($criteria, $context)->getIds();

        return $this->salesChannelIds;
    }

    /**
     * Executes the get default tax id operation.
     *
     * @internal
     */
    private function getDefaultTaxId(Context $context): string
    {
        if ($this->defaultTaxId !== null) {
            return $this->defaultTaxId;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('taxRate', 0));
        $tax = $this->taxRepository->search($criteria, $context)->first();

        if (!$tax) {
        $tax = $this->taxRepository->search(
            new Criteria(),
            $context
        )->first();
        }

        if (! $tax) {
            throw new \RuntimeException(
                'No Tax found in Shopware. Cannot create products.'
            );
        }

        $this->defaultTaxId = $tax->getId();
        return $this->defaultTaxId;
    }

    /**
     * Synchronizes a single product by its Microsoft BC system ID.
     */
    public function syncProduct(string $systemId, ?Context $context = null): void
    {
        $context ??= Context::createDefaultContext();

        $bcProduct = $this->apiClient->fetchProductBySystemId($systemId);
        if (!$bcProduct) {
            $this->logger->warning($this->translator->trans('netformic-bc-integration.productSync.productNotFound'), [
                'systemId' => $systemId,
            ]);

            return;
        }

        $taxId           = $this->getDefaultTaxId($context);
        $salesChannelIds = $this->getAllSalesChannelIds($context);

        $productNumber = trim((string) ($bcProduct['number'] ?? $bcProduct['no'] ?? ''));
        if (empty($productNumber)) {
            return;
        }

        $existingProductIds = $this->getExistingProductIds([$productNumber], $context);
        $itemCategoryCode = $bcProduct['itemCategoryCode'] ?? null;

        if (is_string($itemCategoryCode) && strtoupper(trim($itemCategoryCode)) === 'DISCONTINUED') {
            if (isset($existingProductIds[$productNumber])) {
                $this->productRepository->delete([['id' => $existingProductIds[$productNumber]]], $context);
            }
            return;
        }

        $productData = $this->buildProductData(
            $bcProduct,
            $existingProductIds,
            $taxId,
            $salesChannelIds,
            $context
        );

        if ($productData) {
            $this->productRepository->upsert([$productData], $context);
        }
    }

    /**
     * Builds the product data array for Shopware upsert.
     */
    private function buildProductData(
        array $bcProduct,
        array $existingProductIds,
        string $taxId,
        array $salesChannelIds,
        Context $context
    ): ?array {
        $productNumber    = trim((string) ($bcProduct['number'] ?? $bcProduct['no'] ?? ''));
        $productName      = trim((string) ($bcProduct['description'] ?? ''));
        $unitPrice        = $bcProduct['unitPrice'] ?? null;

        if (empty($productNumber) || empty($productName) || $unitPrice === null || $unitPrice === '') {
            return null;
        }

        $isNewProduct = !isset($existingProductIds[$productNumber]);
        $productId = $existingProductIds[$productNumber] ?? Uuid::randomHex();

        $productData = [
            'id'            => $productId,
            'productNumber' => $productNumber,
            'name'          => $productName,
            'description'   => $bcProduct['description2'] ?? '',
            'stock'         => (int) ($bcProduct['inventory'] ?? 0),
            'active'        => empty($bcProduct['blocked']),
            'isCloseout'    => !($bcProduct['allowInventoryOverride'] ?? false),
            'packUnit'      => null,
            'taxId'         => $taxId,
            'price'         => $this->getPrices($bcProduct, $context->getCurrencyId()),
            'ean'           => $bcProduct['ebbUPCCode'] ?? null,
            'customFields'  => [
                PluginConfig::CUSTOM_FIELD_PRODUCT_SYSTEM_ID->value  => $bcProduct['systemId'] ?? $bcProduct['id'] ?? null,
                PluginConfig::CUSTOM_FIELD_ITEM_TRACKING_CODE->value => $bcProduct['itemTrackingCode'] ?? null,
            ],
        ];

        $categoryIdApi = $bcProduct['itemCategoryId'] ?? null;
        if (! empty($categoryIdApi)) {
            $categoryId = $this->getCategoryIdBySystemId((string) $categoryIdApi, $context);
            if ($categoryId) {
                $productData['categories'] = [
                    ['id' => $categoryId],
                ];
            }
        }

        if ($isNewProduct && ! empty($salesChannelIds)) {
            $visibilities = [];
            foreach ($salesChannelIds as $scId) {
                $visibilities[] = [
                    'salesChannelId' => $scId,
                    'visibility'     => 30,
                ];
            }
            $productData['visibilities'] = $visibilities;
        }

        return $productData;
    }

    /**
     * Get product prices from BC product data.
     *
     * @param array $bcProduct
     * @param mixed $currencyId
     *
     * @return array
     */
    private function getPrices(array $bcProduct, $currencyId)
    {
        if (
            isset($bcProduct['salesPricesOrionMFG'])
            && !empty($bcProduct['salesPricesOrionMFG'])
        ) {
            $currentDate = date('Y-m-d');

            foreach ($bcProduct['salesPricesOrionMFG'] as $priceTier) {
                $startDate = $priceTier['startingDate'];
                $endDate   = $priceTier['endingDate'];

                $isAfterStart = $currentDate >= $startDate;
                $isBeforeEnd  = $currentDate <= $endDate;

                if ($isAfterStart && $isBeforeEnd) {
                    $unitPrice = $priceTier['unitPrice'] ?? null;

                    return [
                        [
                            'currencyId' => $currencyId,
                            'gross'      => (float) $unitPrice,
                            'net'        => (float) $unitPrice,
                            'linked'     => true,
                            'listPrice'  => [
                                'gross'  => $bcProduct['unitPrice'] ?? null,
                                'net'    => $bcProduct['unitPrice'] ?? null,
                                'linked' => true,
                            ],
                        ],
                    ];
                }
            }
        }

        $unitPrice = $bcProduct['unitPrice'] ?? null;

        return [
            [
                'currencyId' => $currencyId,
                'gross'      => (float) $unitPrice,
                'net'        => (float) $unitPrice,
                'linked'     => true,
            ],
        ];
    }
}
