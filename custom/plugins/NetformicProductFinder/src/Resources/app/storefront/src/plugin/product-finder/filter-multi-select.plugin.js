import FilterMultiSelectPlugin from 'src/plugin/listing/filter-multi-select.plugin';

/**
 * Custom multi-select filter plugin for the Product Finder.
 */
export default class ProductFinderFilterMultiSelectPlugin extends FilterMultiSelectPlugin {

    static options = {
        ...FilterMultiSelectPlugin.options,
        parentFilterPanelSelector: '[data-product-finder-listing="true"]'
    };

    /**
     * Initializes the plugin.
     *
     * @private
     */
    _init() {
        this._validateMethods();
        this.init();
        this._preventDropdownClose();

        const container = this.el.closest('.product-finder-filters-container') || document;
        const parentFilterPanelElement = container.querySelector(this.options.parentFilterPanelSelector);
        const finderListing = window.PluginManager.getPluginInstanceFromElement(
            parentFilterPanelElement,
            'ProductFinderListing'
        );

        if (finderListing) {
            this.listing = finderListing;
            finderListing.registerFilter(this);
        }
    }
}
