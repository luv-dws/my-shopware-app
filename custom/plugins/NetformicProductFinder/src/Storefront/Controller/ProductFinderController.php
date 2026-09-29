<?php declare(strict_types=1);

namespace Netformic\ProductFinder\Storefront\Controller;

use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

/**
 * Controller for the Product Finder offcanvas actions.
 * Fetches configured categories, dynamic property groups, and loads corresponding product listings.
 *
 * @package NetformicProductFinder
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
class ProductFinderController extends StorefrontController
{
    /**
     * @param AbstractProductListingRoute $listingRoute
     * @param SystemConfigService $systemConfigService
     * @param EntityRepository $categoryRepository
     */
    public function __construct(
        private readonly AbstractProductListingRoute $listingRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $categoryRepository
    ) {
    }

    /**
     * Renders the product finder offcanvas content based on the selected category and active filters.
     *
     * @param Request $request
     * @param SalesChannelContext $context
     * @return Response
     */
    #[Route(
        '/product-finder/offcanvas',
        name: 'frontend.product-finder.offcanvas',
        methods: ['GET', 'POST'],
        defaults: ['XmlHttpRequest' => true]
    )]
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        // Fetch the administrator-configured tab categories
        $firearmsCategoryId = $this->systemConfigService->get('NetformicProductFinder.config.firearmsCategory');
        $opticsCategoryId = $this->systemConfigService->get('NetformicProductFinder.config.opticsCategory');
        $ammoCategoryId = $this->systemConfigService->get('NetformicProductFinder.config.ammoCategory');

        $allowedCategories = array_values(array_filter([$firearmsCategoryId, $opticsCategoryId, $ammoCategoryId]));

        // Build criteria to query only the allowed and active categories
        $categoryCriteria = new Criteria();
        if (!empty($allowedCategories)) {
            $categoryCriteria->addFilter(new EqualsAnyFilter('id', $allowedCategories));
            $categoryCriteria->addFilter(new EqualsFilter('active', true));
        }

        $fetchedCategories = $this->categoryRepository->search($categoryCriteria, $context->getContext())->getEntities();
        
        // Sort categories to strictly match the order configured in the administration
        $categoriesMap = [];
        foreach ($fetchedCategories as $cat) {
            $categoriesMap[$cat->getId()] = $cat;
        }

        $categories = [];
        foreach ($allowedCategories as $catId) {
            if (isset($categoriesMap[$catId])) {
                $categories[] = $categoriesMap[$catId];
            }
        }

        // Determine default active category
        $defaultCategoryId = !empty($categories) ? $categories[0]->getId() : $context->getSalesChannel()->getNavigationCategoryId();

        $navigationCategoryId = $request->query->get('categoryId');
        if (!$navigationCategoryId) {
            $navigationCategoryId = $defaultCategoryId;
        }

        $listing = $this->listingRoute
            ->load($navigationCategoryId, $request, $context, new Criteria())
            ->getResult();

        // Determine allowed property groups for the active tab
        $activePropertyGroups = [];
        if ($navigationCategoryId === $firearmsCategoryId) {
            $activePropertyGroups = $this->systemConfigService->get('NetformicProductFinder.config.firearmsPropertyGroups') ?: [];
        } elseif ($navigationCategoryId === $opticsCategoryId) {
            $activePropertyGroups = $this->systemConfigService->get('NetformicProductFinder.config.opticsPropertyGroups') ?: [];
        } elseif ($navigationCategoryId === $ammoCategoryId) {
            $activePropertyGroups = $this->systemConfigService->get('NetformicProductFinder.config.ammoPropertyGroups') ?: [];
        }

        $properties = $listing->getAggregations()->get('properties');
        if ($properties instanceof EntityResult) {
            $filteredEntities = $properties->getEntities()->filter(function ($propertyGroup) use ($activePropertyGroups) {
                return in_array($propertyGroup->getId(), $activePropertyGroups, true);
            });

            $listing->getAggregations()->add(new EntityResult('properties', $filteredEntities));
        }

        return $this->renderStorefront(
            '@NetformicProductFinder/storefront/layout/product-finder-offcanvas.html.twig',
            [
                'listing' => $listing,
                'categoryTabs' => $categories,
                'activeCategoryId' => $navigationCategoryId
            ]
        );
    }
}
