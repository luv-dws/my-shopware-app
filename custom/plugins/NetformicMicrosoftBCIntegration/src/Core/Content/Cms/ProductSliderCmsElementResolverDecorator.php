<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\Cms;

use Netformic\MicrosoftBCIntegration\Service\ProductCacheService;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductSliderStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;

/**
 * Class ProductSliderCmsElementResolverDecorator
 *
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class ProductSliderCmsElementResolverDecorator extends AbstractCmsElementResolver
{
    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private readonly CmsElementResolverInterface $decorated,
        private readonly ProductCacheService $productService
    ) {}

    /**
     * Executes the get decorated operation.
     */
    public function getDecorated(): CmsElementResolverInterface
    {
        return $this->decorated;
    }

    /**
     * Executes the get type operation.
     *
     * @internal
     */
    public function getType(): string
    {
        return $this->decorated->getType();
    }

    /**
     * Executes the enrich operation.
     *
     * @internal
     */
    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        // run default enrich behavior from original/decorated resolver
        $this->decorated->enrich($slot, $resolverContext, $result);

        // retrieve sales channel context and ensure customer is logged in
        $salesChannelContext = $resolverContext->getSalesChannelContext();
        if ($salesChannelContext->getCustomer() === null) {
            return;
        }

        // verify the slot data contains a product slider struct
        $slider = $slot->getData();
        if (!$slider instanceof ProductSliderStruct) {
            return;
        }

        // fetch products from the slider collection
        $productsCollection = $slider->getProducts();
        if ($productsCollection === null || $productsCollection->count() === 0) {
            return;
        }

        // fetch, update, and cache live product stock and price from ERP
        $products = $productsCollection->getElements();
        if (($uncached = $this->productService->getUncachedProducts($products))) {
            $this->productService->updateInMemoryProductData(
                $uncached,
                $this->productService->syncProductErpData($uncached, $salesChannelContext->getContext())
            );
        }
    }

    /**
     * Executes the collect operation.
     *
     * @internal
     */
    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        return $this->decorated->collect($slot, $resolverContext);
    }
}
