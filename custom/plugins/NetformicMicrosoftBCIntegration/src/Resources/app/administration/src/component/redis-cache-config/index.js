/**
 * JavaScript module for Netformic Microsoft BC Integration.
 */
import template from './redis-cache-config.html.twig';

const { Component } = Shopware;

/**
 * Administration component for managing the Redis product cache.
 *
 * Allows administrators to:
 *  - Flush cache entries for specific product System IDs.
 *  - Flush all cached product data from Redis.
 */
Component.register('netformic-bc-redis-cache-config', {
    template,

    data() {
        return {
            systemIds: '',
            systemIdsError: null,
            isLoading: false,
            isFlushAllLoading: false,
        };
    },

    computed: {
        /**
         * Returns the parent system configuration component.
         *
         * @returns {Object|null}
         */
        systemConfigComponent() {
            let parent = this.$parent;

            while (parent) {
                if (parent.actualConfigData) {
                    return parent;
                }

                parent = parent.$parent;
            }

            return null;
        },

        /**
         * Returns the current sales channel configuration.
         *
         * @returns {Object|null}
         */
        configData() {
            const systemConfig = this.systemConfigComponent;

            if (!systemConfig) {
                return null;
            }

            const salesChannelId = systemConfig.currentSalesChannelId;

            return systemConfig.actualConfigData?.[salesChannelId];
        }
    },

    methods: {
        /**
         * Flushes Redis cache entries for the specified System IDs.
         *
         * @returns {Promise<void>}
         */
        async flushBySystemId() {
            if (!this.validateSystemIds()) {
                return;
            }

            const systemIds = this.parseSystemIds(this.systemIds);
            this.isLoading = true;

            try {
                const response = await Shopware.Application.getContainer('init').httpClient.post(
                    '/_action/netformic-bc/redis-cache/flush',
                    { systemIds: systemIds.join(',') },
                    {
                        headers: {
                            ...Shopware.Service('syncService').getBasicHeaders(),
                        },
                    }
                );

                this.notifySuccess(response.data.message || this.$tc('global.default.success'));
                this.systemIds = '';
                this.systemIdsError = null;
            } catch (error) {
                this.systemIdsError = {
                    code: 'VALIDATION_ERROR',
                    detail: error.response?.data?.error || this.$tc('global.default.errorMessage'),
                };
            } finally {
                this.isLoading = false;
            }
        },

        /**
         * Flushes all product cache entries from Redis.
         *
         * @returns {Promise<void>}
         */
        async flushAll() {
            this.isFlushAllLoading = true;

            try {
                const response = await Shopware.Application.getContainer('init').httpClient.post(
                    '/_action/netformic-bc/redis-cache/flush-all',
                    {},
                    {
                        headers: {
                            ...Shopware.Service('syncService').getBasicHeaders(),
                        },
                    }
                );

                this.notifySuccess(response.data.message || this.$tc('global.default.success'));
            } catch (error) {
                this.notifyError(error.response?.data?.error || this.$tc('global.default.errorMessage'));
            } finally {
                this.isFlushAllLoading = false;
            }
        },

        /**
         * Validates that at least one System ID has been provided.
         *
         * @returns {boolean}
         */
        validateSystemIds() {
            const systemIds = this.parseSystemIds(this.systemIds);

            this.systemIdsError = systemIds.length > 0
                ? null
                : {
                    code: 'VALIDATION_ERROR',
                    detail: 'System IDs are required',
                };

            return systemIds.length > 0;
        },

        /**
         * Displays a success notification.
         *
         * @param {string} messagetxt
         */
        notifySuccess(messagetxt) {
            const target = this.getNotificationTarget();

            if (target && typeof target.createNotificationSuccess === 'function') {
                target.createNotificationSuccess({ message: messagetxt });
                return;
            }

            if (typeof this.$emit === 'function') {
                this.$emit('notification', {
                    type: 'success',
                    message: messagetxt,
                });
            }
        },

        /**
         * Displays an error notification.
         *
         * @param {string} messagetxt
         */
        notifyError(messagetxt) {
            const target = this.getNotificationTarget();

            if (target && typeof target.createNotificationError === 'function') {
                target.createNotificationError({ message: messagetxt });
                return;
            }

            if (typeof this.$emit === 'function') {
                this.$emit('notification', {
                    type: 'error',
                    message: messagetxt,
                });
            }
        },

        /**
         * Finds the nearest parent component capable of displaying
         * administration notifications.
         *
         * @returns {Object|null}
         */
        getNotificationTarget() {
            let parent = this.$parent;

            while (parent) {
                if (
                    typeof parent.createNotificationSuccess === 'function' ||
                    typeof parent.createNotificationError === 'function'
                ) {
                    return parent;
                }

                parent = parent.$parent;
            }

            return null;
        },

        /**
         * Parses a comma, semicolon, or newline-separated list of
         * System IDs into an array.
         *
         * @param {string} value
         * @returns {string[]}
         */
        parseSystemIds(value) {
            return value
                .split(/[\n,;]+/)
                .map((item) => item.trim())
                .filter(Boolean);
        },
    },
});