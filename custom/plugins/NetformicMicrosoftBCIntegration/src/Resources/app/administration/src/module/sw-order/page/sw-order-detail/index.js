/**
 * JavaScript module for Netformic Microsoft BC Integration.
 */
import template from './sw-order-detail.html.twig';
import './sw-order-detail.scss';

const { Component } = Shopware;

/**
 * @package checkout
 *
 * Override the order detail component to inject a custom "Sync to BC" button
 */
Component.override('sw-order-detail', {
    template,

    computed: {
        /**
         * Checks if the order has already been synchronized with Microsoft Business Central
         * by looking for the custom field.
         *
         * @returns {boolean}
         */
        isOrderSynced() {
            const systemId = this.order?.customFields?.netformic_order_system_id;
            return !!systemId && systemId !== 'FAILED';
        }
    },

    methods: {
        /**
         * Triggers the manual synchronization of the order to Microsoft Business Central
         * and handles the API response notifications.
         *
         * @returns {void}
         */
        onSyncToBC() {
            if (this.isOrderSynced) {
                this.createNotificationInfo({
                    message: this.$tc('netformic-bc.order.alreadySynced'),
                });
                return;
            }

            this.createNotificationInfo({
                message: this.$tc('netformic-bc.order.syncToBcInitiated'),
            });

            const httpClient = Shopware.Application.getContainer('init').httpClient;

            httpClient.post(
                '/_action/netformic-bc/sync-order',
                { orderId: this.orderId },
                { headers: { ...Shopware.Service('syncService').getBasicHeaders() } }
            ).then((response) => {
                if (response.data.success) {
                    this.createNotificationSuccess({
                        message: this.$tc('netformic-bc.order.syncToBcSuccess'),
                    });
                    // Reload order to reflect the newly mapped ID and disable the button
                    this.reloadEntityData();
                } else {
                    this.createNotificationError({
                        message: this.$tc('netformic-bc.order.syncToBcError') + response.data.error,
                    });
                }
            }).catch((error) => {
                this.createNotificationError({
                    message: this.$tc('netformic-bc.order.syncToBcError') + (error.response?.data?.error || error.message),
                });
            });
        }
    }
});
