<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncProductUpsertMessage;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles retrieval and caching of product inventory data from
 * Microsoft Business Central using Redis.
 *
 * The service first attempts to load product data from Redis.
 * Any missing products are fetched from Business Central and
 * subsequently cached for future requests.
 */
class ProductCacheService
{
    private const DEFAULT_CACHE_TTL = 3600; // 1 Hour

    /**
     * @internal
     */
    public function __construct(
        private readonly RedisService $redisService,
        private readonly BcApiClient $bcApiClient,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $productRepository,
        private readonly ?MessageBusInterface $messageBus = null
    ) {
    }

    /**
     * Filters the given list of product entities and returns a map of
     *
     * @param array $products Array of Shopware product entities
     *
     * @return array<string, object> Map of systemId -> ProductEntity
     */
    public function getUncachedProducts(array $products): array
    {
        if (empty($products)) {
            return [];
        }

        $redisKeyMap = [];
        $uncachedProducts = [];

        foreach ($products as $product) {
            $systemId = ($product->getCustomFields() ?? [])[PluginConfig::CUSTOM_FIELD_PRODUCT_SYSTEM_ID->value] ?? null;
            if (!$systemId) {
                continue;
            }

            $redisKey = $this->getRedisKey((string) $systemId);
            $redisKeyMap[$redisKey] = [
                'systemId' => (string) $systemId,
                'product' => $product,
            ];
        }

        if (empty($redisKeyMap)) {
            return [];
        }

        // Single bulk mget query to check cache state for all products
        $cachedMap = $this->redisService->fetchMultiple(array_keys($redisKeyMap));

        foreach ($redisKeyMap as $redisKey => $item) {
            if (empty($cachedMap[$redisKey])) {
                $uncachedProducts[$item['systemId']] = $item['product'];
            }
        }

        return $uncachedProducts;
    }

    /**
     * Updates the in-memory product entity fields for the current request context.
     * This is used on product detail and listing pages so that the updated price
     * and stock values are rendered immediately on the first page load.
     *
     * @param array $uncachedProducts Map of systemId -> ProductEntity
     * @param array $erpProducts      Array of fetched ERP product data arrays
     *
     * @return void
     */
    public function updateInMemoryProductData(array $uncachedProducts, array $erpProducts): void
    {
        if (empty($uncachedProducts) || empty($erpProducts)) {
            return;
        }

        foreach ($erpProducts as $erpProduct) {
            $systemId = $erpProduct['systemId'] ?? null;
            if (!$systemId || !isset($uncachedProducts[$systemId])) {
                continue;
            }

            $product = $uncachedProducts[$systemId];
            $inventory = isset($erpProduct['inventory']) ? (int) $erpProduct['inventory'] : null;
            $unitPrice = isset($erpProduct['unitPrice']) ? (float) $erpProduct['unitPrice'] : null;

            if ($inventory !== null) {
                $maxPurchase = $product->getMaxPurchase() ?? 100;
                $product->setAvailableStock($inventory);
                $product->setMaxPurchase($inventory > 0 ? min($maxPurchase, $inventory) : 0);
            }

            if ($unitPrice !== null && method_exists($product, 'getCalculatedPrice')) {
                $calculatedPrice = $product->getCalculatedPrice();
                if ($calculatedPrice) {
                    $calculatedPrice->overwrite($unitPrice, $unitPrice, new CalculatedTaxCollection());
                }
            }
        }
    }

    /**
     * Ensures product price and stock are up to date in the database and cache.
     *
     * Persists the fresh ERP pricing and stock directly to the database via upsert,
     * and caches it in Redis.
     *
     * @param array $uncachedProducts Map of systemId -> ProductEntity
     * @param Context $context
     *
     * @return array Array of fetched ERP product data arrays
     */
    public function syncProductErpData(array $uncachedProducts, Context $context): array
    {
        if (empty($uncachedProducts)) {
            return [];
        }

        $upsertData = [];
        $fetchedProducts = [];
        $redisBatch = [];
        $cacheTtl = $this->getCacheTtl();

        try {
            $apiResult = $this->fetchErpProductsFromApi(array_keys($uncachedProducts));
            $bcProducts = $apiResult['data'] ?? [];
        } catch (\Throwable $e) {
            // Log timeout / API failure gracefully so storefront page rendering does not break
            return [];
        }

        foreach ($bcProducts as $erpProduct) {
            $systemId = $erpProduct['systemId'] ?? null;
            if (!$systemId || !isset($uncachedProducts[$systemId])) {
                continue;
            }

            $unitPrice = isset($erpProduct['unitPrice']) ? (float) $erpProduct['unitPrice'] : null;
            $inventory = isset($erpProduct['inventory']) ? (int) $erpProduct['inventory'] : null;

            // Build the upsert payload with whatever fields the API returned
            $upsertItem = ['id' => $uncachedProducts[$systemId]->getId()];
            if ($unitPrice !== null) {
                $upsertItem['price'] = [
                    [
                        'currencyId' => $context->getCurrencyId(),
                        'gross' => $unitPrice,
                        'net' => $unitPrice,
                        'linked' => true,
                    ]
                ];
            }
            if ($inventory !== null) {
                $upsertItem['stock'] = $inventory;
            }
            $upsertData[] = $upsertItem;

            $redisBatch[$this->getRedisKey((string) $systemId)] = $erpProduct;
            $fetchedProducts[] = $erpProduct;
        }

        // Save all ERP products to Redis in a single bulk operation
        if (!empty($redisBatch)) {
            $this->redisService->createMultiple($redisBatch, $cacheTtl);
        }

        // Persist price and stock changes asynchronously via message queue to avoid blocking page load
        if (!empty($upsertData)) {
            if ($this->messageBus !== null) {
                $this->messageBus->dispatch(new SyncProductUpsertMessage($upsertData));
            } else {
                $this->productRepository->upsert($upsertData, $context);
            }
        }

        return $fetchedProducts;
    }

    /**
     * Retrieves product ERP data for the given system IDs.
     *
     * Cached data is returned from Redis when available.
     * Missing products are retrieved from Business Central and
     * stored in Redis for subsequent requests.
     *
     * @param array $systemIds
     *
     * @return array
     */
    public function getProductErpData(array $systemIds): array
    {
        if (empty($systemIds)) {
            return [];
        }

        $erpProducts = [];
        $missingSystemIds = [];
        $redisKeyMap = [];

        foreach ($systemIds as $systemId) {
            $redisKeyMap[$this->getRedisKey((string) $systemId)] = (string) $systemId;
        }

        // Single bulk mget query
        $cachedMap = $this->redisService->fetchMultiple(array_keys($redisKeyMap));

        foreach ($redisKeyMap as $redisKey => $systemId) {
            if (!empty($cachedMap[$redisKey])) {
                $erpProducts[] = $cachedMap[$redisKey];
            } else {
                $missingSystemIds[] = $systemId;
            }
        }

        if (!empty($missingSystemIds)) {
            try {
                $fetchedErpProducts = $this->fetchErpProductsFromApi($missingSystemIds)['data'] ?? [];
            } catch (\Throwable $e) {
                $fetchedErpProducts = [];
            }
            $redisBatch = [];
            $cacheTtl = $this->getCacheTtl();

            foreach ($fetchedErpProducts as $erpProduct) {
                if (!isset($erpProduct['systemId'])) {
                    continue;
                }

                $redisBatch[$this->getRedisKey((string) $erpProduct['systemId'])] = $erpProduct;
                $erpProducts[] = $erpProduct;
            }

            if (!empty($redisBatch)) {
                $this->redisService->createMultiple($redisBatch, $cacheTtl);
            }
        }

        return $erpProducts;
    }

    /**
     * Retrieves product information from Microsoft Business Central.
     *
     * @param array $systemIds
     *
     * @return array
     */
    private function fetchErpProductsFromApi(array $systemIds): array
    {
        $filterConditions = array_map(function ($id) {
            $cleanId = trim(str_replace(["'", '%27'], '', $id));

            return "systemId eq {$cleanId}";
        }, $systemIds);

        $queryParams = [
            '$top' => count($systemIds),
            '$filter' => implode(' or ', $filterConditions),
            '$select' => 'id,no,inventory,unitPrice,systemId',
        ];

        $endpoint = $this->buildUrl() . '?' . http_build_query($queryParams);

        return $this->bcApiClient->fetchProducts($endpoint);
    }

    /**
     * Removes cached product data from Redis.
     *
     * If no System IDs are provided, all product cache entries
     * managed by this integration are removed.
     *
     * @param string|array|null $systemIds
     *
     * @return int Number of deleted cache entries.
     */
    public function flushProductCache(string|array|null $systemIds = null): int
    {
        if ($systemIds === null || $systemIds === '') {
            return $this->redisService->flushByPrefix([PluginConfig::REDIS_KEY_PREFIX->value]);
        }

        if (is_string($systemIds)) {
            $systemIds = array_values(
                array_filter(
                    array_map('trim', preg_split('/[\n,;]+/', $systemIds) ?: [])
                )
            );
        } elseif (is_array($systemIds)) {
            $systemIds = array_values(array_filter(array_map('trim', $systemIds)));
        }

        if (empty($systemIds)) {
            return 0;
        }

        $deleted = 0;

        foreach ($systemIds as $systemId) {
            $deleted += $this->redisService->delete($this->getRedisKey($systemId));
        }

        return $deleted;
    }

    /**
     * Generates the Redis cache key for a product.
     *
     * @param string $systemId
     *
     * @return string
     */
    private function getRedisKey(string $systemId): string
    {
        return PluginConfig::REDIS_KEY_PREFIX->value . md5($systemId);
    }

    /**
     * Returns the configured Redis cache lifetime.
     *
     * Falls back to the default TTL when no valid configuration
     * value is available.
     *
     * @return int
     */
    private function getCacheTtl(): int
    {
        $ttl = $this->systemConfigService->getInt(
            PluginConfig::CONFIG_REDIS_CACHE_TTL->value
        );

        return $ttl > 0 ? $ttl : self::DEFAULT_CACHE_TTL;
    }

    /**
     * Builds the Microsoft Business Central API endpoint
     * for retrieving product information.
     *
     * @return string
     */
    private function buildUrl(): string
    {
        $mode = $this->systemConfigService->getBool(PluginConfig::CONFIG_CURRENT_API_MODE->value) ? 'sandbox' : 'production';

        $domain = PluginConfig::CONFIG_DOMAIN->value;

        $apiUrl = $this->systemConfigService->getString(
            "{$domain}{$mode}CustomApiUrl"
        );

        $companyId = $this->systemConfigService->getString(
            PluginConfig::CONFIG_COMPANY_ID->value
        );

        return sprintf(
            '%s/companies(%s)/itemsOrionMFG',
            rtrim($apiUrl, '/'),
            $companyId
        );
    }
}