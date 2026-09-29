// Main entry point for the Product Finder storefront JS bundle to register custom plugins
import ProductFinderOffcanvasPlugin from './plugin/product-finder-offcanvas/product-finder-offcanvas.plugin';
import ProductFinderPlugin from './plugin/product-finder/product-finder.plugin';

const PluginManager = window.PluginManager;

PluginManager.register(
    'ProductFinderOffcanvas',
    ProductFinderOffcanvasPlugin,
    '[data-product-finder-offcanvas]'
);

PluginManager.register(
    'ProductFinder',
    ProductFinderPlugin,
    '[data-product-finder]'
);

PluginManager.register(
    'ProductFinderListing',
    () => import('./plugin/product-finder/listing.plugin'),
    '[data-product-finder-listing]'
);

PluginManager.register(
    'ProductFinderFilterMultiSelect',
    () => import('./plugin/product-finder/filter-multi-select.plugin'),
    '[data-product-finder-filter-multi-select]'
);

PluginManager.register(
    'ProductFinderFilterPropertySelect',
    () => import('./plugin/product-finder/filter-property-select.plugin'),
    '[data-product-finder-filter-property-select]'
);

PluginManager.register(
    'ProductFinderFilterRange',
    () => import('./plugin/product-finder/filter-range.plugin'),
    '[data-product-finder-filter-range]'
);

PluginManager.register(
    'ProductFinderFilterRatingSelect',
    () => import('./plugin/product-finder/filter-rating-select.plugin'),
    '[data-product-finder-filter-rating-select]'
);

PluginManager.register(
    'ProductFinderFilterBoolean',
    () => import('./plugin/product-finder/filter-boolean.plugin'),
    '[data-product-finder-filter-boolean]'
);
