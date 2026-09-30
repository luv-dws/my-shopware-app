import template from './sw-product-detail.html.twig';

const { Component } = Shopware;

/**
 * Override the product detail component to inject a custom "Sync from BC" button
 */
Component.override('sw-product-detail', {
    template,

    computed: {
        /**
         * Checks if the product has a Microsoft Business Central system ID mapping.
         *
         * @returns {boolean}
         */
        hasBcSystemId() {
            return !!this.product?.customFields?.netformic_bc_product_system_id;
        }
    },

    methods: {
        /**
         * Triggers the manual synchronization of the product from Business Central.
         *
         * @returns {void}
         */
        onSyncFromBC() {
            const systemId = this.product?.customFields?.netformic_bc_product_system_id;
            if (!systemId) {
                return;
            }

            this.createNotificationInfo({
                message: this.$tc('netformic-bc.product.syncFromBcInitiated'),
            });

            const httpClient = Shopware.Application.getContainer('init').httpClient;

            httpClient.post(
                '/_action/netformic-bc/sync-product',
                { systemId: systemId },
                { headers: { ...Shopware.Service('syncService').getBasicHeaders() } }
            ).then((response) => {
                if (response.data.success) {
                    this.createNotificationSuccess({
                        message: this.$tc('netformic-bc.product.syncFromBcSuccess'),
                    });
                    // Reload product to reflect the newly synchronized data
                    this.loadAll();
                } else {
                    this.createNotificationError({
                        message: this.$tc('netformic-bc.product.syncFromBcError') + response.data.error,
                    });
                }
            }).catch((error) => {
                this.createNotificationError({
                    message: this.$tc('netformic-bc.product.syncFromBcError') + (error.response?.data?.error || error.message),
                });
            });
        }
    }
});
