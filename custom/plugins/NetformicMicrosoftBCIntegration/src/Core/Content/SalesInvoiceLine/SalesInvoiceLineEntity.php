<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoiceLine;

use Shopware\Core\Content\Product\ProductEntity;
use Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoice\SalesInvoiceEntity;

/**
 * Class SalesInvoiceLineEntity
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class SalesInvoiceLineEntity extends \Shopware\Core\Framework\DataAbstractionLayer\Entity
{
    protected string $id;

    protected string $invoiceId;

    protected ?SalesInvoiceEntity $invoice;

    protected ?string $systemId;

    protected ?string $productId;

    protected ?ProductEntity $product;

    protected ?string $itemNumber;

    protected ?string $description;

    protected ?float $unitPrice;

    protected ?float $quantity;

    protected ?string $unitOfMeasureCode;

    protected ?float $netAmountIncludingTax;

    protected ?\DateTimeInterface $shipmentDate;

    /**
     * Executes the get id operation.
     *
     * @internal
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Executes the set id operation.
     *
     * @internal
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Executes the get invoice id operation.
     *
     * @internal
     */
    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    /**
     * Executes the set invoice id operation.
     *
     * @internal
     */
    public function setInvoiceId(string $invoiceId): void
    {
        $this->invoiceId = $invoiceId;
    }

    /**
     * Executes the get invoice operation.
     *
     * @internal
     */
    public function getInvoice(): ?SalesInvoiceEntity
    {
        return $this->invoice;
    }

    /**
     * Executes the set invoice operation.
     *
     * @internal
     */
    public function setInvoice(?SalesInvoiceEntity $invoice): void
    {
        $this->invoice = $invoice;
    }

    /**
     * Executes the get system id operation.
     *
     * @internal
     */
    public function getSystemId(): ?string
    {
        return $this->systemId;
    }

    /**
     * Executes the set system id operation.
     *
     * @internal
     */
    public function setSystemId(?string $systemId): void
    {
        $this->systemId = $systemId;
    }

    /**
     * Executes the get product id operation.
     *
     * @internal
     */
    public function getProductId(): ?string
    {
        return $this->productId;
    }

    /**
     * Executes the set product id operation.
     *
     * @internal
     */
    public function setProductId(?string $productId): void
    {
        $this->productId = $productId;
    }

    /**
     * Executes the get product operation.
     *
     * @internal
     */
    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    /**
     * Executes the set product operation.
     *
     * @internal
     */
    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    /**
     * Executes the get item number operation.
     *
     * @internal
     */
    public function getItemNumber(): ?string
    {
        return $this->itemNumber;
    }

    /**
     * Executes the set item number operation.
     *
     * @internal
     */
    public function setItemNumber(?string $itemNumber): void
    {
        $this->itemNumber = $itemNumber;
    }

    /**
     * Executes the get description operation.
     *
     * @internal
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Executes the set description operation.
     *
     * @internal
     */
    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    /**
     * Executes the get unit price operation.
     *
     * @internal
     */
    public function getUnitPrice(): ?float
    {
        return $this->unitPrice;
    }

    /**
     * Executes the set unit price operation.
     *
     * @internal
     */
    public function setUnitPrice(?float $unitPrice): void
    {
        $this->unitPrice = $unitPrice;
    }

    /**
     * Executes the get quantity operation.
     *
     * @internal
     */
    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    /**
     * Executes the set quantity operation.
     *
     * @internal
     */
    public function setQuantity(?float $quantity): void
    {
        $this->quantity = $quantity;
    }

    /**
     * Executes the get unit of measure code operation.
     *
     * @internal
     */
    public function getUnitOfMeasureCode(): ?string
    {
        return $this->unitOfMeasureCode;
    }

    /**
     * Executes the set unit of measure code operation.
     *
     * @internal
     */
    public function setUnitOfMeasureCode(?string $unitOfMeasureCode): void
    {
        $this->unitOfMeasureCode = $unitOfMeasureCode;
    }

    /**
     * Executes the get net amount including tax operation.
     *
     * @internal
     */
    public function getNetAmountIncludingTax(): ?float
    {
        return $this->netAmountIncludingTax;
    }

    /**
     * Executes the set net amount including tax operation.
     *
     * @internal
     */
    public function setNetAmountIncludingTax(?float $netAmountIncludingTax): void
    {
        $this->netAmountIncludingTax = $netAmountIncludingTax;
    }

    /**
     * Executes the get shipment date operation.
     *
     * @internal
     */
    public function getShipmentDate(): ?\DateTimeInterface
    {
        return $this->shipmentDate;
    }

    /**
     * Executes the set shipment date operation.
     *
     * @internal
     */
    public function setShipmentDate(?\DateTimeInterface $shipmentDate): void
    {
        $this->shipmentDate = $shipmentDate;
    }

}
