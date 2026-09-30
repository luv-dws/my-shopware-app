<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Subscriber;

use Netformic\MicrosoftBCIntegration\Service\ProductCacheService;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Shopware\Storefront\Page\Wishlist\WishlistPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for storefront product pages, listings, search results, and wishlists.
 * Syncs real-time ERP inventory and pricing data for all displayed products.
 */
class ProductSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductCacheService $productservice
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
            ProductListingResultEvent::class => 'onProductListingLoaded',
            ProductSearchResultEvent::class => 'onProductListingLoaded',
            WishlistPageLoadedEvent::class => 'onWishlistPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        if ($event->getSalesChannelContext()->getCustomer() === null) {
            return;
        }

        if ($product = $event->getPage()->getProduct()) {
            $this->syncProducts([$product], $event->getSalesChannelContext());
        }
    }

    public function onProductListingLoaded(ProductListingResultEvent $event): void
    {
        if ($event->getSalesChannelContext()->getCustomer() === null) {
            return;
        }

        $products = $event->getResult()->getEntities()->getElements();
        $this->syncProducts($products, $event->getSalesChannelContext());
    }

    public function onWishlistPageLoaded(WishlistPageLoadedEvent $event): void
    {
        if ($event->getSalesChannelContext()->getCustomer() === null) {
            return;
        }

        $wishlistPage = $event->getPage();
        $wishlist = $wishlistPage->getWishlist();
        if ($wishlist && $productListing = $wishlist->getProductListing()) {
            $this->syncProducts($productListing->getEntities()->getElements(), $event->getSalesChannelContext());
        }
    }

    /**
     * Helper to sync and update ERP product data in bulk.
     */
    private function syncProducts(array $products, SalesChannelContext $salesChannelContext): void
    {
        if (empty($products)) {
            return;
        }

        $uncached = $this->productservice->getUncachedProducts($products);
        if (!empty($uncached)) {
            $this->productservice->updateInMemoryProductData(
                $uncached,
                $this->productservice->syncProductErpData($uncached, $salesChannelContext->getContext())
            );
        }
    }
}
