<?php declare(strict_types=1);

namespace Netformic\ProductFinder\Storefront\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCollectFilterEvent;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber to dynamically collect and register custom filters
 * (such as stock status and selected categories) into the product listing collection.
 */
class ListingFilterSubscriber implements EventSubscriberInterface
{
    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCollectFilterEvent::class => 'addFilters',
        ];
    }

    /**
     * Appends dynamic storefront filters (in-stock and categories) to the product listing collection.
     *
     * @param ProductListingCollectFilterEvent $event
     * @return void
     */
    public function addFilters(ProductListingCollectFilterEvent $event): void
    {
        $filters = $event->getFilters();
        $request = $event->getRequest();

        // In-Stock Filter
        $inStockFiltered = (bool) $request->get('only-in-stock');
        $inStockDalFilter = new RangeFilter('product.availableStock', [
            RangeFilter::GT => 0,
        ]);

        $inStockFilter = new Filter(
            'only-in-stock',
            $inStockFiltered,
            [
                new MaxAggregation('only-in-stock', 'product.availableStock'),
            ],
            $inStockDalFilter,
            $inStockFiltered
        );
        $filters->add($inStockFilter);

        // Category Filter
        $categoryIds = $request->get('categories');
        if (\is_string($categoryIds)) {
            $categoryIds = explode('|', $categoryIds);
        }
        $categoryIds = array_filter((array) $categoryIds);

        // Fallback to active categoryId or navigationId if categories parameter is empty
        if (empty($categoryIds)) {
            $fallbackCategory = $request->get('categoryId') ?: $request->get('navigationId');
            if (!empty($fallbackCategory)) {
                $categoryIds = [(string) $fallbackCategory];
            }
        }

        // Only mark as filtered if a category is selected or supplied via fallback
        $categoryFiltered = !empty($categoryIds);
        // Use product.categoryTree (stored as a JSON list directly on product table)
        // This avoids the JoinGroup conflict with Shopware's internal product.categoriesRo.id filter
        $categoryDalFilter = new EqualsAnyFilter('product.categoryTree', $categoryIds);
        $categoryFilter = new Filter(
            'categories',
            $categoryFiltered,
            [
                new EntityAggregation('categories', 'product.categories.id', 'category'),
            ],
            $categoryDalFilter,
            $categoryIds
        );
        $filters->add($categoryFilter);
    }
}
