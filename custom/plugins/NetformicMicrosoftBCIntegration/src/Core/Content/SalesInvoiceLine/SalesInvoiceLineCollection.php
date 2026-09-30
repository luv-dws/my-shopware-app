<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoiceLine;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SalesInvoiceLineEntity>
 * @method void add(SalesInvoiceLineEntity $entity)
 * @method void set(string $key, SalesInvoiceLineEntity $entity)
 * @method SalesInvoiceLineEntity[] getIterator()
 * @method SalesInvoiceLineEntity[] getElements()
 * @method SalesInvoiceLineEntity|null get(string $key)
 * @method SalesInvoiceLineEntity|null first()
 * @method SalesInvoiceLineEntity|null last()
 */
class SalesInvoiceLineCollection extends EntityCollection
{
    /**
     * Executes the get expected class operation.
     *
     * @internal
     */
    protected function getExpectedClass(): string
    {
        return SalesInvoiceLineEntity::class;
    }
}
