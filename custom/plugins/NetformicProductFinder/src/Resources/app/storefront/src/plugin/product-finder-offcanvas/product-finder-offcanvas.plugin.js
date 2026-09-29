import Plugin from 'src/plugin-system/plugin.class';
import AjaxOffCanvas from 'src/plugin/offcanvas/ajax-offcanvas.plugin';

/**
 * Product Finder offcanvas trigger plugin.
 */
export default class ProductFinderOffcanvasPlugin extends Plugin {
    static options = {
        offcanvasSelector: '.offcanvas'
    };

    /**
     * Initializes the plugin.
     */
    init() {
        this._onClick = this._onClick.bind(this);
        this.el.addEventListener('click', this._onClick);
    }

    /**
     * Handles the click event on the trigger element.
     *
     * @param {Event} event
     * @private
     */
    _onClick(event) {
        event.preventDefault();

        const url = this.el.getAttribute('href') || this.el.dataset.url;
        if (!url) {
            return;
        }

        // Open offcanvas and prevent standard filter JS plugins from initializing
        AjaxOffCanvas.open(url, false, () => { }, 'left');
    }
}