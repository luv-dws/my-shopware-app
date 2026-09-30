<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoiceLine;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoice\SalesInvoiceDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Symfony\Config\Framework\Workflows\WorkflowConfig\PlaceConfig;

/**
 * Class SalesInvoiceLineDefinition
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class SalesInvoiceLineDefinition extends EntityDefinition
{
    /**
     * Executes the get entity name operation.
     *
     * @internal
     */
    public function getEntityName(): string
    {
        return PluginConfig::SALES_INVOICE_LINE_ENTITY_NAME->value;
    }

    /**
     * Executes the get entity class operation.
     *
     * @internal
     */
    public function getEntityClass(): string
    {
        return SalesInvoiceLineEntity::class;
    }

    /**
     * Executes the get collection class operation.
     *
     * @internal
     */
    public function getCollectionClass(): string
    {
        return SalesInvoiceLineCollection::class;
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
            
            (new FkField('invoice_id', 'invoiceId', SalesInvoiceDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('invoice', 'invoice_id', SalesInvoiceDefinition::class, 'id', false),
            
            new StringField('system_id', 'systemId'),
            
            new FkField('product_id', 'productId', ProductDefinition::class),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
            
            new StringField('item_number', 'itemNumber'),
            new StringField('description', 'description'),
            new FloatField('unit_price', 'unitPrice'),
            new FloatField('quantity', 'quantity'),
            new StringField('unit_of_measure_code', 'unitOfMeasureCode'),
            new FloatField('net_amount_including_tax', 'netAmountIncludingTax'),
            new DateTimeField('shipment_date', 'shipmentDate'),
        ]);
    }
}
