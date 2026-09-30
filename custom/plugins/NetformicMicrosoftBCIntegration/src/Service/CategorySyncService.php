<?php

declare (strict_types = 1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Synchronizes category data from Microsoft Business Central into Shopware.
 */
class CategorySyncService
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private BcApiClient $apiClient,
        private EntityRepository $categoryRepository,
        private LoggerInterface $logger,
        private SystemConfigService $systemConfigService,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Main category synchronization process.
     *
     * Fetches categories from BC, creates or updates categories in Shopware,
     * and deactivates categories that no longer exist in BC.
     */
    public function syncCategories(): void
    {
        $context = Context::createDefaultContext();

        try {
            $bcCategories = $this->apiClient->fetchCategories();

            if (empty($bcCategories)) {
                $this->logger->info($this->translator->trans('netformic-bc-integration.categorySync.noCategoriesFetched'));
                return;
            }

            $rootCategoryId = $this->systemConfigService->getString(
                PluginConfig::CONFIG_ROOT_CATEGORY_ID->value
            );

            // Collect all BC system IDs for lookup and deletion detection
            $allSystemIds = $this->extractSystemIds($bcCategories);

            // Load existing Shopware category IDs mapped by BC system ID
            $shopwareIdsBySystemId = $this->getExistingShopwareIds($allSystemIds, $context);

            $upsertData = [];

            foreach ($bcCategories as $bcCategory) {
                if (empty($bcCategory['systemId'])) {
                    continue;
                }

                // Reuse existing category ID or generate a new one
                $isNewCategory = ! isset($shopwareIdsBySystemId[$bcCategory['systemId']]);

                $categoryId = $shopwareIdsBySystemId[$bcCategory['systemId']] ?? Uuid::randomHex();

                $shopwareIdsBySystemId[$bcCategory['systemId']] = $categoryId;

                $categoryData = $this->mapCategoryData($categoryId, $bcCategory);

                // Assign configured root category only for newly created categories
                if ($isNewCategory && ! empty($rootCategoryId)) {
                    $categoryData['parentId'] = $rootCategoryId;
                }

                $upsertData[] = $categoryData;

                // Process child categories
                $subCategories = $bcCategory['itemCategoriesSubOrionMFG'] ?? [];

                if (is_array($subCategories)) {
                    foreach ($subCategories as $subCategory) {
                        if (empty($subCategory['systemId'])) {
                            continue;
                        }

                        $subCategoryId = $shopwareIdsBySystemId[$subCategory['systemId']] ?? Uuid::randomHex();

                        $shopwareIdsBySystemId[$subCategory['systemId']] = $subCategoryId;

                        $subCategoryData = $this->mapCategoryData(
                            $subCategoryId,
                            $subCategory
                        );

                        // Link subcategory to its parent category
                        $subCategoryData['parentId'] = $categoryId;

                        $upsertData[] = $subCategoryData;
                    }
                }
            }

            // Find categories removed from BC and mark them inactive
            $deletedCategoryIds = $this->getDeletedCategoryIds(
                $allSystemIds,
                $context
            );

            foreach ($deletedCategoryIds as $deletedId) {
                $upsertData[] = [
                    'id'     => $deletedId,
                    'active' => false,
                ];
            }

            // Persist all category changes
            if (! empty($upsertData)) {
                $this->categoryRepository->upsert($upsertData, $context);
            }

            $this->logger->info($this->translator->trans('netformic-bc-integration.categorySync.syncSuccess'));
        } catch (\Throwable $e) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.categorySync.syncFailed'), [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Extracts all BC system IDs from parent and child categories.
     *
     * @param array<int, array<string, mixed>> $bcCategories
     *
     * @return array<int, string>
     */
    private function extractSystemIds(array $bcCategories): array
    {
        $ids = [];

        foreach ($bcCategories as $bcCategory) {
            if (! empty($bcCategory['systemId'])) {
                $ids[] = $bcCategory['systemId'];
            }

            $subCategories = $bcCategory['itemCategoriesSubOrionMFG'] ?? [];

            if (is_array($subCategories)) {
                foreach ($subCategories as $subCategory) {
                    if (! empty($subCategory['systemId'])) {
                        $ids[] = $subCategory['systemId'];
                    }
                }
            }
        }

        return array_unique($ids);
    }

    /**
     * Retrieves existing Shopware category IDs indexed by BC system ID.
     *
     * @param array<int, string> $systemIds
     *
     * @return array<string, string>
     */
    private function getExistingShopwareIds(array $systemIds, Context $context): array
    {
        if (empty($systemIds)) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsAnyFilter('customFields.' . PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value, $systemIds)
        );

        $existingCategories = $this->categoryRepository->search($criteria, $context);

        $mapping = [];

        foreach ($existingCategories as $category) {
            $customFields = $category->getCustomFields() ?? [];

            if (! empty($customFields[PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value])) {
                $mapping[$customFields[PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value]] = $category->getId();
            }
        }

        return $mapping;
    }

    /**
     * Maps Microsoft BC category data into Shopware category payload format.
     *
     * @param array<string, mixed> $bcData
     *
     * @return array<string, mixed>
     */
    private function mapCategoryData(string $id, array $bcData): array
    {
        return [
            'id'           => $id,
            'name'         => $bcData['code'] ?? 'Unknown Category',
            'description'  => $bcData['description'] ?? '',
            'active'       => true,
            'customFields' => [
                PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value => $bcData['systemId'],
            ],
        ];
    }

    /**
     * Finds Shopware categories previously synced from BC
     * that no longer exist in the latest BC response.
     *
     * @param array<int, string> $currentSystemIds
     *
     * @return array<int, string>
     */
    private function getDeletedCategoryIds(array $currentSystemIds, Context $context): array
    {
        $criteria = new Criteria();

        $criteria->addFilter(
            new NotFilter(
                NotFilter::CONNECTION_AND,
                [
                    new EqualsFilter('customFields.' . PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value, null),
                ]
            )
        );

        if (! empty($currentSystemIds)) {
            $criteria->addFilter(
                new NotFilter(
                    NotFilter::CONNECTION_AND,
                    [
                        new EqualsAnyFilter(
                            'customFields.' . PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value,
                            $currentSystemIds
                        ),
                    ]
                )
            );
        }

        return $this->categoryRepository
            ->searchIds($criteria, $context)
            ->getIds();
    }
}
