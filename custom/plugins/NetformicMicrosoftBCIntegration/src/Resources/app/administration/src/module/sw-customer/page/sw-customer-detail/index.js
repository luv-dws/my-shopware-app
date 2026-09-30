import template from './sw-customer-detail.html.twig';

const { Component } = Shopware;

/**
 * Override the customer detail component to inject a custom "Sync from BC" button
 */
Component.override('sw-customer-detail', {
    template,

    computed: {
        /**
         * Checks if the customer has a Microsoft Business Central system ID mapping.
         *
         * @returns {boolean}
         */
        hasBcSystemId() {
            return !!this.customer?.customFields?.netformic_bc_customer_system_id;
        }
    },

    methods: {
        /**
         * Triggers the manual synchronization of the customer from Business Central.
         *
         * @returns {void}
         */
        onSyncFromBC() {
            const systemId = this.customer?.customFields?.netformic_bc_customer_system_id;
            if (!systemId) {
                return;
            }

            this.createNotificationInfo({
                message: this.$tc('netformic-bc.customer.syncFromBcInitiated'),
            });

            const httpClient = Shopware.Application.getContainer('init').httpClient;

            httpClient.post(
                '/_action/netformic-bc/sync-customer',
                { systemId: systemId },
                { headers: { ...Shopware.Service('syncService').getBasicHeaders() } }
            ).then((response) => {
                if (response.data.success) {
                    this.createNotificationSuccess({
                        message: this.$tc('netformic-bc.customer.syncFromBcSuccess'),
                    });
                    // Reload customer to reflect the newly synchronized data
                    this.loadCustomer();
                } else {
                    this.createNotificationError({
                        message: this.$tc('netformic-bc.customer.syncFromBcError') + response.data.error,
                    });
                }
            }).catch((error) => {
                this.createNotificationError({
                    message: this.$tc('netformic-bc.customer.syncFromBcError') + (error.response?.data?.error || error.message),
                });
            });
        }
    }
});
