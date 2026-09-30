<?php

namespace Netformic\MicrosoftBCIntegration\Core\Content;

/**
 * Central list of configuration, custom field, route, form, and snippet keys used by the plugin.
 */
enum PluginConfig : string
{
    /** System Config Domain */
    case CONFIG_DOMAIN = 'NetformicMicrosoftBCIntegration.config.';

    /** System Config Root Category ID */
    case CONFIG_ROOT_CATEGORY_ID = 'NetformicMicrosoftBCIntegration.config.rootCategoryId';

    /** System config key for the selected serialized-product cart rule. */
    case CONFIG_CART_CONTAINS_SERIALIZED_RULE_ID = 'NetformicFflDropshipping.config.cartContainsSerializedRuleId';

    /** System config key for the selected FFL validation rule. */
    case CONFIG_FFL_VALIDATION_RULE_ID = 'NetformicFflDropshipping.config.fflValidationRuleId';

    /** System Config Current API Mode */
    case CONFIG_CURRENT_API_MODE = 'NetformicMicrosoftBCIntegration.config.currentApiMode';

    /** System Config Company ID */
    case CONFIG_COMPANY_ID = 'NetformicMicrosoftBCIntegration.config.companyId';

    /** Batch Size Configs */
    case CONFIG_CUSTOMER_SYNC_BATCH_SIZE = 'NetformicMicrosoftBCIntegration.config.customerSyncBatchSize';
    case CONFIG_PRODUCT_SYNC_BATCH_SIZE = 'NetformicMicrosoftBCIntegration.config.productSyncBatchSize';
    case CONFIG_INVENTORY_SYNC_BATCH_SIZE = 'NetformicMicrosoftBCIntegration.config.inventorySyncBatchSize';
    case CONFIG_PRICING_SYNC_BATCH_SIZE = 'NetformicMicrosoftBCIntegration.config.pricingSyncBatchSize';
    case CONFIG_ORDER_STATUS_SYNC_BATCH_SIZE = 'NetformicMicrosoftBCIntegration.config.orderStatusSyncBatchSize';
    case CONFIG_ENABLE_ORDER_SYNC_FAILURE_EMAIL = 'NetformicMicrosoftBCIntegration.config.enableOrderSyncFailureEmail';
    case CONFIG_ORDER_SYNC_FAILURE_EMAIL_TEMPLATE_ID = 'NetformicMicrosoftBCIntegration.config.orderSyncFailureEmailTemplateId';

    /** Custom Field Sets */
    case CUSTOM_FIELD_SET_CATEGORY = 'netformic_bc_category_set';
    case CUSTOM_FIELD_SET_PRODUCT = 'netformic_bc_product_set';
    case CUSTOM_FIELD_SET_CUSTOMER = 'netformic_bc_customer_set';
    case CUSTOM_FIELD_SET_ORDER = 'netformic_bc_order_set';
    case CUSTOM_FIELD_SET_PAYMENT_METHOD = 'netformic_bc_payment_method_set';
    case CUSTOM_FIELD_SET_SHIPPING_METHOD = 'netformic_bc_shipping_method_set';
    case CUSTOM_FIELD_SET_ADDRESS = 'netformic_bc_address_set';

    /** Category Custom Fields */
    case CUSTOM_FIELD_CATEGORY_SYSTEM_ID = 'netformic_category_system_id';
    case CUSTOM_FIELD_CATEGORY_IS_TAYLOR_CUSTOMS = 'netformic_category_is_taylor_customs';

    /** Product Custom Fields */
    case CUSTOM_FIELD_PRODUCT_SYSTEM_ID = 'netformic_product_system_id';
    case CUSTOM_FIELD_ITEM_TRACKING_CODE = 'netformic_product_item_tracking_code';

    /** Customer Custom Fields */
    case CUSTOM_FIELD_CUSTOMER_SYSTEM_ID = 'netformic_customer_system_id';
    case CUSTOM_FIELD_BALANCE = 'netformic_customer_balance';
    case CUSTOM_FIELD_CREDIT_AMOUNT = 'netformic_customer_credit_amount';
    case CUSTOM_FIELD_CONTACT = 'netformic_customer_contact';
    case CUSTOM_FIELD_SALES_REP_CODE = 'netformic_customer_sales_rep_code';

    /** Order Custom Fields */
    case CUSTOM_FIELD_ORDER_SYSTEM_ID = 'netformic_order_system_id';
    case CUSTOM_FIELD_BC_ORDER_NUMBER = 'netformic_order_bc_number';
    case CUSTOM_FIELD_FFL_EXPIRY = 'netformic_order_ffl_expiry';
    case CUSTOM_FIELD_FFL_DOCUMENT_CODE = 'netformic_order_ffl_document_code';
    case CUSTOM_FIELD_FFL_REQUIRED = 'netformic_order_ffl_required';
    case CUSTOM_FIELD_ORDER_FULFILLMENT_NUMBER = 'netformic_order_fulfillment_number';
    case CUSTOM_FIELD_IS_PAYFABRIC_SYNCED = 'netformic_order_is_payfabric_synced';

    /** Payment/Shipping Method Custom Fields */
    case CUSTOM_FIELD_PAYMENT_METHOD_SYSTEM_ID = 'netformic_payment_method_system_id';
    case CUSTOM_FIELD_SHIPPING_METHOD_SYSTEM_ID = 'netformic_shipping_method_system_id';
    case CUSTOM_FIELD_PAYFABRIC_TRX_KEY = 'netformic_order_transaction_id';

    /** Address Custom Fields */
    case CUSTOM_FIELD_ADDRESS_FFL_EXPIRY = 'netformic_customer_address_ffl_expiry';
    case CUSTOM_FIELD_ADDRESS_FFL_DOCUMENT_CODE = 'netformic_customer_address_ffl_document_code';
    case CUSTOM_FIELD_IS_BC_ADDRESS = 'netformic_customer_address_is_bc_address';

    /** FFL Document Field */
    case CUSTOM_FIELD_FFL_FILE_PATH = 'netformic_customer_address_ffl_file_path';

    /** Snippets */
    case SNIPPET_FFL_WARNING = 'netformic-bc-integration.checkout.fflWarning';

    /** Redis cache ttl */
    case CONFIG_REDIS_CACHE_TTL = 'NetformicMicrosoftBCIntegration.config.redisCacheTtl';

    /** BC Order Statuses */
    case BC_STATUS_OPEN = 'Open';
    case BC_STATUS_RELEASED = 'Released';
    case BC_STATUS_PENDING_APPROVAL = 'Pending Approval';
    case BC_STATUS_PENDING_PREPAYMENT = 'Pending Prepayment';
    case BC_STATUS_AWAITING_PAYMENT_CLEARING = 'Awaiting Payment Clearing';

    /** Sales Invoice Entity name */
    case SALES_INVOICE_ENTITY_NAME = 'netformic_bc_sales_invoice';

    /** Sales Invoice Line Entity name */
    case SALES_INVOICE_LINE_ENTITY_NAME = 'netformic_bc_sales_invoice_line';

    /** Redis cache prefix */
    case REDIS_KEY_PREFIX = 'bc_product:';

    /** ATF EZ Check URL */
    case ATF_EZ_CHECK_URL = "https://fflezcheck.atf.gov/FFLEzCheck/fflSearch"; 
}
