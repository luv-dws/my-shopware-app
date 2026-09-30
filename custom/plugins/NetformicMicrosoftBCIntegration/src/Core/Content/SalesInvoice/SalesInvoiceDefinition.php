<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoice;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoiceLine\SalesInvoiceLineDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

/**
 * Class SalesInvoiceDefinition
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class SalesInvoiceDefinition extends EntityDefinition
{
    /**
     * Executes the get entity name operation.
     *
     * @internal
     */
    public function getEntityName(): string
    {
        return PluginConfig::SALES_INVOICE_ENTITY_NAME->value;
    }

    /**
     * Executes the get entity class operation.
     *
     * @internal
     */
    public function getEntityClass(): string
    {
        return SalesInvoiceEntity::class;
    }

    /**
     * Executes the get collection class operation.
     *
     * @internal
     */
    public function getCollectionClass(): string
    {
        return SalesInvoiceCollection::class;
    }

    /**
     * Executes the define fields operation.
     *
     * @internal
     */
    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            
            new StringField('system_id', 'systemId'),
            new StringField('number', 'number'),
            new StringField('external_document_number', 'externalDocumentNumber'),
            new DateTimeField('invoice_date', 'invoiceDate'),
            new DateTimeField('due_date', 'dueDate'),
            
            new FkField('customer_id', 'customerId', CustomerDefinition::class),
            new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, 'id', false),
            
            new FkField('order_id', 'orderId', OrderDefinition::class),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            
            new StringField('currency_code', 'currencyCode'),
            new FloatField('total_amount_including_tax', 'totalAmountIncludingTax'),
            new FloatField('discount_amount', 'discountAmount'),
            new FloatField('remaining_amount', 'remainingAmount'),
            
            new StringField('salesperson', 'salesperson'),
            new StringField('payment_terms_id', 'paymentTermsId'),
            new StringField('shipment_method_id', 'shipmentMethodId'),
            new StringField('status', 'status'),
            
            new StringField('bill_to_name', 'billToName'),
            new StringField('bill_to_address_line_1', 'billToAddressLine1'),
            new StringField('bill_to_address_line_2', 'billToAddressLine2'),
            new StringField('bill_to_city', 'billToCity'),
            new StringField('bill_to_country', 'billToCountry'),
            new StringField('bill_to_state', 'billToState'),
            new StringField('bill_to_post_code', 'billToPostCode'),
            
            new StringField('ship_to_name', 'shipToName'),
            new StringField('ship_to_contact', 'shipToContact'),
            new StringField('ship_to_address_line_1', 'shipToAddressLine1'),
            new StringField('ship_to_address_line_2', 'shipToAddressLine2'),
            new StringField('ship_to_city', 'shipToCity'),
            new StringField('ship_to_country', 'shipToCountry'),
            new StringField('ship_to_state', 'shipToState'),
            new StringField('ship_to_post_code', 'shipToPostCode'),
            
            new StringField('sell_to_address_line_1', 'sellToAddressLine1'),
            new StringField('sell_to_address_line_2', 'sellToAddressLine2'),
            new StringField('sell_to_city', 'sellToCity'),
            new StringField('sell_to_country', 'sellToCountry'),
            new StringField('sell_to_state', 'sellToState'),
            new StringField('sell_to_post_code', 'sellToPostCode'),
            
            (new OneToManyAssociationField('lineItems', SalesInvoiceLineDefinition::class, 'invoice_id', 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}
