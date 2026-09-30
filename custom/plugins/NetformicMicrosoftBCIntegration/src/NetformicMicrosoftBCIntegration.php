<?php

declare (strict_types = 1);

namespace Netformic\MicrosoftBCIntegration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\CustomField\CustomFieldTypes;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;

/**
 * Main plugin class for the Microsoft BC Integration.
 */
class NetformicMicrosoftBCIntegration extends Plugin
{
    /**
     * Runs when the plugin is installed.
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        // Create required custom fields during installation
        $this->createCustomFields($installContext->getContext());
    }

    /**
     * Runs when the plugin is uninstalled.
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->deleteCustomFields($uninstallContext->getContext());

        /** @var Connection $connection */
        $connection = $this->container->get(Connection::class);
        $connection->executeStatement('DROP TABLE IF EXISTS `netformic_bc_sales_invoice_line`');
        $connection->executeStatement('DROP TABLE IF EXISTS `netformic_bc_sales_invoice`');
    }

    /**
     * Runs when the plugin is activated.
     */
    public function activate(ActivateContext $activateContext): void
    {
        // Register or enable plugin-related functionality
    }

    /**
     * Runs when the plugin is deactivated.
     */
    public function deactivate(DeactivateContext $deactivateContext): void
    {
        // Disable plugin-related functionality
    }

    /**
     * Runs when the plugin is updated.
     */
    public function update(UpdateContext $updateContext): void
    {
        parent::update($updateContext);

        // Ensure custom fields exist after updates
        $this->createCustomFields($updateContext->getContext());
    }

    /**
     * Runs after installation is completed.
     */
    public function postInstall(InstallContext $installContext): void
    {
    }

    /**
     * Runs after an update is completed.
     */
    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    /**
     * Creates the custom field set used to store
     * Microsoft BC identifiers on categories.
     */
    private function createCustomFields(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        try {
        $customFieldSetRepository->upsert([
            // Category BC Sync Data
            [
                'id'           => '019ef8354faf73308d2a9b307809dacf',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_CATEGORY->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Category',
                        'de-DE' => 'Microsoft BC Sync Data - Kategorie',
                    ],
                ],
                'relations'    => [
                    ['id' => '019ef8354fec72a2a90c83132dadfae8', 'entityName' => 'category'],
                ],
                'customFields' => [
                    [
                        'id'     => '019ef4017da978e78dabf8ff18dfa46a',
                        'name'   => PluginConfig::CUSTOM_FIELD_CATEGORY_SYSTEM_ID->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC System ID', 'de-DE' => 'BC System-ID'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => false,
                    ],
                    [
                        'id'     => '019f2a354fc77217bcb3855ad800000a',
                        'name'   => PluginConfig::CUSTOM_FIELD_CATEGORY_IS_TAYLOR_CUSTOMS->value,
                        'type'   => CustomFieldTypes::BOOL,
                        'config' => [
                            'label'               => ['en-GB' => 'Taylor Customs', 'de-DE' => 'Taylor Customs'],
                            'componentName'       => 'sw-switch-field',
                            'type'                => 'switch',
                            'customFieldType'     => 'switch',
                            'customFieldPosition' => 2,
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => false,
                    ],
                ],
            ],
            // Product BC Sync Data
            [
                'id'           => '019ef8354faf73308d2a9b307809db10',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_PRODUCT->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Product',
                        'de-DE' => 'Microsoft BC Sync Data - Produkt',
                    ],
                ],
                'relations'    => [
                    ['id' => '019ef8354fec72a2a90c83132e1ed2c9', 'entityName' => 'product'],
                ],
                'customFields' => [
                    [
                        // Reuse the existing field ID — custom field names are globally unique
                        'id'     => '019ef4017da978e78dabf8ff18dfa46b',
                        'name'   => PluginConfig::CUSTOM_FIELD_PRODUCT_SYSTEM_ID->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC System ID', 'de-DE' => 'BC System-ID'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354faf73308d2a9b307809db01',
                        'name'   => PluginConfig::CUSTOM_FIELD_ITEM_TRACKING_CODE->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC Item Tracking Code', 'de-DE' => 'BC Artikel-Trackingcode'],
                            'customFieldPosition' => 2,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => true,
                        'storeApiAware' => true,
                    ],
                ],
            ],
            // Customer Financial Data
            [
                'id'           => '019ef8354faf73308d2a9b307809daee',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_CUSTOMER->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Customer',
                        'de-DE' => 'Microsoft BC Sync Data - Kunde',
                    ],
                ],
                'relations'    => [
                    ['id' => '019ef8354faf73308d2a9b307809daef', 'entityName' => 'customer'],
                ],
                'customFields' => [
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000000',
                        'name'   => PluginConfig::CUSTOM_FIELD_CUSTOMER_SYSTEM_ID->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC System ID', 'de-DE' => 'BC System-ID'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad66b4e17',
                        'name'   => PluginConfig::CUSTOM_FIELD_BALANCE->value,
                        'type'   => CustomFieldTypes::FLOAT,
                        'config' => [
                            'label'               => ['en-GB' => 'Balance'],
                            'customFieldPosition' => 2,
                            'componentName'       => 'sw-field',
                            'type'                => 'number',
                            'numberType'          => 'float',
                            'customFieldType'     => 'number',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad70ce1fc',
                        'name'   => PluginConfig::CUSTOM_FIELD_CREDIT_AMOUNT->value,
                        'type'   => CustomFieldTypes::FLOAT,
                        'config' => [
                            'label'               => ['en-GB' => 'Credit Amount'],
                            'customFieldPosition' => 4,
                            'componentName'       => 'sw-field',
                            'type'                => 'number',
                            'numberType'          => 'float',
                            'customFieldType'     => 'number',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8deee11',
                        'name'   => PluginConfig::CUSTOM_FIELD_CONTACT->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC Contact Name', 'de-DE' => 'BC Kontaktname'],
                            'customFieldPosition' => 7,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8deee12',
                        'name'   => PluginConfig::CUSTOM_FIELD_SALES_REP_CODE->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'Sales Rep Code', 'de-DE' => 'Vertretercode'],
                            'customFieldPosition' => 8,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                ],
            ],
            // Order BC Sync Data
            [
                'id'           => '019ef8354faf73308d2a9b307809dae0',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_ORDER->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Order',
                        'de-DE' => 'Microsoft BC Sync Data - Bestellung',
                    ],
                ],
                'relations'    => [
                    ['id' => '019ef8354faf73308d2a9b307809dae1', 'entityName' => 'order'],
                ],
                'customFields' => [
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000001',
                        'name'   => PluginConfig::CUSTOM_FIELD_ORDER_SYSTEM_ID->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC System ID', 'de-DE' => 'BC System-ID'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000004',
                        'name'   => PluginConfig::CUSTOM_FIELD_FFL_EXPIRY->value,
                        'type'   => CustomFieldTypes::DATETIME,
                        'config' => [
                            'label'               => ['en-GB' => 'FFL Expiry Date', 'de-DE' => 'FFL Ablaufdatum'],
                            'customFieldPosition' => 2,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'date',
                            'dateType'            => 'datetime',
                            'customFieldType'     => 'date',
                        ],
                        'allowCustomerWrites' => true,
                        'allowCartExpose' => true,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000005',
                        'name'   => PluginConfig::CUSTOM_FIELD_FFL_DOCUMENT_CODE->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'FFL Document Code', 'de-DE' => 'FFL Dokumentcode'],
                            'customFieldPosition' => 3,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => true,
                        'allowCartExpose' => true,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000006',
                        'name'   => PluginConfig::CUSTOM_FIELD_FFL_REQUIRED->value,
                        'type'   => CustomFieldTypes::BOOL,
                        'config' => [
                            'label'               => ['en-GB' => 'FFL Required', 'de-DE' => 'FFL Erforderlich'],
                            'customFieldPosition' => 4,
                            'componentName'       => 'sw-switch-field',
                            'type'                => 'switch',
                            'customFieldType'     => 'switch',
                        ],
                        'allowCustomerWrites' => true,
                        'allowCartExpose' => true,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019f2a354fc77217bcb3855ad800000b',
                        'name'   => PluginConfig::CUSTOM_FIELD_ORDER_FULFILLMENT_NUMBER->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'Order Fulfillment Number', 'de-DE' => 'Bestellabwicklungsnummer'],
                            'customFieldPosition' => 5,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019f2a354fc77217bcb3855ad800000c',
                        'name'   => PluginConfig::CUSTOM_FIELD_BC_ORDER_NUMBER->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC Order Number', 'de-DE' => 'BC Bestellnummer'],
                            'customFieldPosition' => 6,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019f2a354fc77217bcb3855ad800000d',
                        'name'   => PluginConfig::CUSTOM_FIELD_IS_PAYFABRIC_SYNCED->value,
                        'type'   => CustomFieldTypes::BOOL,
                        'config' => [
                            'label'               => ['en-GB' => 'PayFabric Transaction Synced', 'de-DE' => 'PayFabric Transaktion synchronisiert'],
                            'customFieldPosition' => 7,
                            'disabled'            => true,
                            'componentName'       => 'sw-switch-field',
                            'type'                => 'switch',
                            'customFieldType'     => 'switch',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                ],
            ],
            // Payment Method BC Sync Data
            [
                'id'           => '019ef8354faf73308d2a9b307809dae2',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_PAYMENT_METHOD->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Payment Method',
                        'de-DE' => 'Microsoft BC Sync Data - Zahlungsart',
                    ],
                ],
                'relations'    => [
                    ['id' => '019ef8354faf73308d2a9b307809dae3', 'entityName' => 'payment_method'],
                ],
                'customFields' => [
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000002',
                        'name'   => PluginConfig::CUSTOM_FIELD_PAYMENT_METHOD_SYSTEM_ID->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC System ID', 'de-DE' => 'BC System-ID'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                ],
            ],
            // Shipping Method BC Sync Data
            [
                'id'           => '019ef8354faf73308d2a9b307809dae4',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_SHIPPING_METHOD->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Shipping Method',
                        'de-DE' => 'Microsoft BC Sync Data - Versandart',
                    ],
                ],
                'relations'    => [
                    ['id' => '019ef8354faf73308d2a9b307809dae5', 'entityName' => 'shipping_method'],
                ],
                'customFields' => [
                    [
                        'id'     => '019ef8354fc77217bcb3855ad8000003',
                        'name'   => PluginConfig::CUSTOM_FIELD_SHIPPING_METHOD_SYSTEM_ID->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'BC System ID', 'de-DE' => 'BC System-ID'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                ],
            ],
            // Address BC Sync Data
            [
                'id'           => '019f2a354faf73308d2a9b307809dae6',
                'name'         => PluginConfig::CUSTOM_FIELD_SET_ADDRESS->value,
                'config'       => [
                    'label' => [
                        'en-GB' => 'Microsoft BC Sync Data - Address',
                        'de-DE' => 'Microsoft BC Sync Data - Adresse',
                    ],
                ],
                'relations'    => [
                    ['id' => '019f2a354faf73308d2a9b307809dae7', 'entityName' => 'customer_address'],
                ],
                'customFields' => [
                    [
                        'id'     => '019f2a354fc77217bcb3855ad8000007',
                        'name'   => PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_EXPIRY->value,
                        'type'   => CustomFieldTypes::DATETIME,
                        'config' => [
                            'label'               => ['en-GB' => 'FFL Expiry Date', 'de-DE' => 'FFL Ablaufdatum'],
                            'customFieldPosition' => 1,
                            'disabled'            => true,
                            'componentName'       => 'sw-field',
                            'type'                => 'date',
                            'dateType'            => 'datetime',
                            'customFieldType'     => 'date',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id'     => '019f2a354fc77217bcb3855ad8000008',
                        'name'   => PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_DOCUMENT_CODE->value,
                        'type'   => CustomFieldTypes::TEXT,
                        'config' => [
                            'label'               => ['en-GB' => 'FFL Document Code', 'de-DE' => 'FFL Dokumentcode'],
                            'customFieldPosition' => 2,
                            'componentName'       => 'sw-field',
                            'type'                => 'text',
                            'customFieldType'     => 'text',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                    [
                        'id' => '019f2a354fc77217bcb3855ad8000009',
                        'name' => PluginConfig::CUSTOM_FIELD_IS_BC_ADDRESS->value,
                        'type' => CustomFieldTypes::BOOL,
                        'config' => [
                            'label' => ['en-GB' => 'Is BcAddress', 'de-DE' => 'Is BcAddress'],
                            'customFieldPosition' => 3,
                            'disabled' => true,
                            'componentName'       => 'sw-switch-field',
                            'type'                => 'switch',
                            'customFieldType'     => 'switch',
                        ],
                        'allowCustomerWrites' => false,
                        'allowCartExpose' => false,
                        'storeApiAware' => true,
                    ],
                ],
            ],
        ], $context);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Deletes the custom field sets created by this plugin.
     */
    private function deleteCustomFields(Context $context): void
    {
        $customFieldSetRepository = $this->container->get('custom_field_set.repository');

        $names = [
            PluginConfig::CUSTOM_FIELD_SET_CATEGORY->value,
            PluginConfig::CUSTOM_FIELD_SET_PRODUCT->value,
            PluginConfig::CUSTOM_FIELD_SET_CUSTOMER->value,
            PluginConfig::CUSTOM_FIELD_SET_ORDER->value,
            PluginConfig::CUSTOM_FIELD_SET_PAYMENT_METHOD->value,
            PluginConfig::CUSTOM_FIELD_SET_SHIPPING_METHOD->value,
            PluginConfig::CUSTOM_FIELD_SET_ADDRESS->value,
        ];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('name', $names));

        try {
            $customFieldSet = $customFieldSetRepository->search($criteria, $context);
            $elements = $customFieldSet->getEntities()->getElements();

            foreach ($elements as $id => $value) {
                try {
                    $customFieldSetRepository->delete([['id' => $id]], $context);
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
