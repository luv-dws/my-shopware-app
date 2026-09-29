import ListingPlugin from 'src/plugin/listing/listing.plugin';

/**
 * Custom listing plugin for the Product Finder.
 */
export default class ProductFinderListingPlugin extends ListingPlugin {
    static options = {
        ...ListingPlugin.options,
        searchName: 'search',
        categoriesName: 'categories',
        booleanCheckboxSelector: '.filter-boolean-input'
    };

    /**
     * Initializes the plugin.
     *
     * @public
     */
    init() {
        super.init();

        const form = this.el.closest('form');

        if (form) {
            form.addEventListener('submit', this._onFormSubmit.bind(this));
        }
        this._urlFilterParams = {};

        // ListingPlugin.init() sets internal properties via global document.querySelector(),
        // which on a category page finds elements from the STANDARD listing, not the offcanvas.
        // Re-assign them scoped to this.el so all downstream methods naturally operate on the correct elements.
        const container = this.el.closest('.product-finder-filters-container') || document;
        this._filterPanel = container.querySelector(this.options.filterPanelSelector);
        this._filterPanelActive = !!this._filterPanel;
        this._cmsProductListingWrapper = this.el.querySelector(this.options.cmsProductListingWrapperSelector) || this.el;
        this._cmsProductListingWrapperActive = !!this._cmsProductListingWrapper;
        this.activeFilterContainer = container.querySelector(this.options.activeFilterContainerSelector);
        this.ariaLiveContainer = container.querySelector(this.options.ariaLiveSelector);

        this._fixLabelClicks();
        this._updateResultCount();
    }

    /**
     * Fixes label clicks for custom checkboxes.
     *
     * @private
     */
    _fixLabelClicks() {
        if (!this._filterPanelActive) return;

        const labels = this._filterPanel.querySelectorAll('label[for]');
        labels.forEach(label => {
            label.removeAttribute('for');
            label.addEventListener('click', (e) => {
                e.preventDefault();
                // Find the associated input inside the label's parent wrapper (e.g. form-check div)
                const input = label.parentElement.querySelector('input');
                if (input && !input.disabled) {
                    if (input.type === 'checkbox') {
                        input.checked = !input.checked;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    } else if (input.type === 'radio' && !input.checked) {
                        input.checked = true;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            });
        });
    }

    /**
     * Renders AJAX listing response.
     *
     * @param {string} response
     * @public
     */
    renderResponse(response) {
        // Parse the AJAX response and replace only the matching content WITHIN this.el.
        const selector = this.options.cmsProductListingSelector;
        const responseDoc = new DOMParser().parseFromString(response, 'text/html');
        const newContent = responseDoc.querySelector(selector);
        const currentContent = this.el.matches(selector) ? this.el : this.el.querySelector(selector);

        if (newContent && currentContent) {
            // Strip attributes that would trigger standard listing helper plugins (sorting, pagination, limit, view changer)
            // since they expect the standard "Listing" plugin to be active, not "ProductFinderListing".
            const attributesToStrip = [
                'data-listing-sorting',
                'data-listing-limit',
                'data-listing-pagination',
                'data-netformic-listing-view-changer',
            ];
            attributesToStrip.forEach(attr => {
                newContent.querySelectorAll(`[${attr}]`).forEach(el => el.removeAttribute(attr));
                if (newContent.hasAttribute(attr)) {
                    newContent.removeAttribute(attr);
                }
            });

            if (currentContent === this.el) {
                this.el.innerHTML = newContent.innerHTML;
            } else {
                currentContent.replaceWith(newContent.cloneNode(true));
            }
        }

        this._registry.forEach((item) => {
            if (typeof item.afterContentChange === 'function') {
                item.afterContentChange();
            }
        });

        // Scope initialization to only the offcanvas listing container, NOT the whole page
        window.PluginManager.initializePluginsInParentElement(this.el);

        this._updateResultCount();

        this.$emitter.publish('Listing/afterRenderResponse', { response });
    }

    /**
     * Updates active query params cache.
     *
     * @param {URLSearchParams} queryParams
     * @private
     */
    _updateHistory(queryParams) {
        this._currentQueryParams = queryParams;
    }

    /**
     * Updates the bottom display count text.
     *
     * @private
     */
    _updateResultCount() {
        const displayCount = document.getElementById('product-finder-result-count-display');
        if (!displayCount) return;

        const template = displayCount.getAttribute('data-results-template') || 'Search result : %count%';

        const jsListingWrapper = this.el.querySelector('.js-listing-wrapper');
        if (jsListingWrapper) {
            const ariaLiveText = jsListingWrapper.getAttribute('data-aria-live-text');
            if (ariaLiveText) {
                const matches = ariaLiveText.match(/\d+/g);
                if (matches && matches.length > 0) {
                    const total = matches[matches.length - 1];
                    displayCount.innerHTML = template.replace('%count%', total);
                }
            }
        }
    }

    /**
     * Handles the form submit event.
     *
     * @param {Event} event
     * @private
     */
    _onFormSubmit(event) {
        event.preventDefault();
        const form = event.target;
        const params = this._currentQueryParams || new URLSearchParams();

        // 1. If categories parameter is missing (e.g. initial load without selections), add default category input value
        if (!params.has(this.options.categoriesName)) {
            const categoryInput = form.querySelector(`input[name="${this.options.categoriesName}"][type="hidden"]`);
            if (categoryInput && categoryInput.value) {
                params.set(this.options.categoriesName, categoryInput.value);
            }
        }

        // 2. Also check if text search input has a value, as text changes don't trigger the listing AJAX updates automatically
        const searchInput = form.querySelector(`input[name="${this.options.searchName}"]`);
        if (searchInput && searchInput.value) {
            params.set(this.options.searchName, searchInput.value);
        }

        // Redirect to the search results page
        const actionUrl = form.getAttribute('action') || '/search';
        window.location.href = `${actionUrl}?${params.toString()}`;
    }
}
