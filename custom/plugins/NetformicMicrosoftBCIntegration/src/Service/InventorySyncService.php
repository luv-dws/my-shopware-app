<?php declare (strict_types = 1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncInventoryMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Package('inventory')]
/**
 * Service for synchronizing product inventory from Microsoft Business Central to Shopware.
 */

class InventorySyncService
{
    /**
     * @internal
     */
    public function __construct(
        private BcApiClient $apiClient,
        private EntityRepository $productRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private SystemConfigService $systemConfigService,
        private TranslatorInterface $translator
    ) {}

    /**
     * Executes the inventory synchronization for a batch of products.
     *
     * @param int $offset The pagination offset for querying products from Shopware
     * @param int $take The number of products to fetch in this batch
     *
     * @return void
     */
    public function syncInventory(int $offset, int $take): void
    {
        $context = Context::createDefaultContext();

        // Fetch a batch of products from Shopware
        $criteria = new Criteria();
        $criteria->setOffset($offset);
        $criteria->setLimit($take);
        
        // Ensure we only process products that actually have a product number
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('productNumber', null)]));

        $products = $this->productRepository->search($criteria, $context);

        // If no more products in Shopware, we are done!
        if ($products->count() === 0) {
            $this->logger->info($this->translator->trans('netformic-bc-integration.inventorySync.datasetEndReached'));
            return;
        }

        $existingProductIds = [];
        $productNumbers = [];

        foreach ($products as $product) {
            $existingProductIds[$product->getProductNumber()] = $product->getId();
            $productNumbers[] = $product->getProductNumber();
        }

        if (empty($productNumbers)) {
            $this->logger->info($this->translator->trans('netformic-bc-integration.inventorySync.noMoreProducts'));
            return;
        }

        // Build the OData $filter string for BC
        $filterParts = array_map(function ($num) {
            return sprintf("no eq '%s'", str_replace("'", "''", $num));
        }, $productNumbers);
        
        $filterString = implode(' or ', $filterParts);

        $mode      = $this->systemConfigService->getBool(PluginConfig::CONFIG_CURRENT_API_MODE->value) ? 'sandbox' : 'production';
        $domain    = PluginConfig::CONFIG_DOMAIN->value;
        $apiUrl    = rtrim($this->systemConfigService->getString("{$domain}{$mode}CustomApiUrl"), '/');
        $companyId = $this->systemConfigService->getString(PluginConfig::CONFIG_COMPANY_ID->value);

        if (!$apiUrl || !$companyId) {
            return;
        }

        $endpoint = sprintf(
            '%s/companies(%s)/itemsOrionMFG?$select=id,no,inventory,systemId&$filter=%s',
            $apiUrl,
            $companyId,
            rawurlencode($filterString)
        );

        // Fetch products from Microsoft Business Central
        $response    = $this->apiClient->fetchProducts($endpoint, $take);
        $bcProducts  = $response['data'] ?? [];

        $upsertData = [];

        // Map Business Central inventory data to Shopware product format
        foreach ($bcProducts as $bcProduct) {
            try {
                $productNumber = trim((string) ($bcProduct['number'] ?? $bcProduct['no'] ?? ''));

                if (empty($productNumber) || !isset($existingProductIds[$productNumber])) {
                    continue; // Only update existing products
                }

                $productId = $existingProductIds[$productNumber];

                // Build the payload for upserting the product stock
                $upsertData[] = [
                    'id'    => $productId,
                    'stock' => (int) ($bcProduct['inventory'] ?? 0),
                ];
            } catch (\Throwable $e) {
                // Silently skip mapping errors
            }
        }

        // Upsert the updated inventory data into the Shopware product repository
        if (!empty($upsertData)) {
            try {
                $this->productRepository->upsert($upsertData, $context);
            } catch (\Throwable $e) {
                $this->logger->error($this->translator->trans('netformic-bc-integration.inventorySync.batchUpsertFailed'), [
                    'productIds' => array_column($upsertData, 'id'),
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        // Dispatch message for the next batch only if we fetched a full batch
        if (count($productNumbers) === $take) {
            $this->messageBus->dispatch(new SyncInventoryMessage($offset + $take, $take));
        } else {
            $this->logger->info($this->translator->trans('netformic-bc-integration.inventorySync.syncCompleted'));
        }
    }
}
