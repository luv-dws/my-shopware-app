import Plugin from 'src/plugin-system/plugin.class';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

/**
 * Product Finder plugin.
 */
export default class ProductFinderPlugin extends Plugin {
    static options = {
        offcanvasUrl: '/product-finder/offcanvas',
        categoryBtnSelector: '.btn-category',
        offcanvasSelector: '.offcanvas',
        toggleBtnSelector: '.filter-panel-wrapper-toggle',
        wrapperSelector: '.filter-panel-wrapper',
    };

    /**
     * Initializes the plugin.
     */
    init() {
        this._prepareForm();

        // Listen for category tab clicks using event delegation on the form
        this.el.addEventListener('click', this._onCategoryClick.bind(this));
    }

    /**
     * Prepares the form layout and mobile interactions.
     *
     * @private
     */
    _prepareForm() {
        const container = this.el.closest(this.options.offcanvasSelector) || this.el;

        // Add local toggle logic for mobile filter wrapper toggle
        const toggleBtn = container.querySelector(this.options.toggleBtnSelector);
        const wrapper = container.querySelector(this.options.wrapperSelector);
        if (toggleBtn && wrapper) {
            toggleBtn.addEventListener('click', (event) => {
                event.preventDefault();
                if (window.getComputedStyle(wrapper).display === 'none') {
                    wrapper.style.setProperty('display', 'block', 'important');
                } else {
                    wrapper.style.setProperty('display', 'none', 'important');
                }
            });
        }

        // Prevent dropdown menus from closing when clicking inside them
        container.querySelectorAll(this.options.dropdownMenuSelector).forEach((dropdownMenu) => {
            dropdownMenu.addEventListener('click', (event) => {
                event.stopPropagation();
            });
        });
    }

    /**
     * Handles category button clicks.
     *
     * @param {Event} event
     * @private
     */
    _onCategoryClick(event) {
        const btn = event.target.closest(this.options.categoryBtnSelector);
        if (!btn) {
            return;
        }

        event.preventDefault();

        const baseUrl = this.el.dataset.offcanvasUrl || this.options.offcanvasUrl;
        const categoryId = btn.dataset.categoryId;
        const fetchUrl = `${baseUrl}?categoryId=${categoryId}`;

        AjaxOffCanvas.setContent(fetchUrl, false, () => {
            // Callback when offcanvas content has loaded
        });
    }
}
