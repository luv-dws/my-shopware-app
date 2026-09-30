<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoice;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<SalesInvoiceEntity>
 * @method void add(SalesInvoiceEntity $entity)
 * @method void set(string $key, SalesInvoiceEntity $entity)
 * @method SalesInvoiceEntity[] getIterator()
 * @method SalesInvoiceEntity[] getElements()
 * @method SalesInvoiceEntity|null get(string $key)
 * @method SalesInvoiceEntity|null first()
 * @method SalesInvoiceEntity|null last()
 */
class SalesInvoiceCollection extends EntityCollection
{
    /**
     * Executes the get expected class operation.
     *
     * @internal
     */
    protected function getExpectedClass(): string
    {
        return SalesInvoiceEntity::class;
    }
}
