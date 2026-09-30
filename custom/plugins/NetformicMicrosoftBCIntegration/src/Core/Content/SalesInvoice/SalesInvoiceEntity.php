<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoice;

use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Netformic\MicrosoftBCIntegration\Core\Content\SalesInvoiceLine\SalesInvoiceLineCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

/**
 * Class SalesInvoiceEntity
 * 
 * Core component of the Netformic Microsoft BC Integration plugin.
 */
class SalesInvoiceEntity extends Entity
{
    protected string $id;

    protected ?string $systemId;

    protected ?string $number;

    protected ?string $externalDocumentNumber;

    protected ?\DateTimeInterface $invoiceDate;

    protected ?\DateTimeInterface $dueDate;

    protected ?string $customerId;

    protected ?CustomerEntity $customer;

    protected ?string $orderId;

    protected ?OrderEntity $order;

    protected ?string $currencyCode;

    protected ?float $totalAmountIncludingTax;

    protected ?float $discountAmount;

    protected ?float $remainingAmount;

    protected ?string $salesperson;

    protected ?string $paymentTermsId;

    protected ?string $shipmentMethodId;

    protected ?string $status;

    protected ?string $billToName;

    protected ?string $billToAddressLine1;

    protected ?string $billToAddressLine2;

    protected ?string $billToCity;

    protected ?string $billToCountry;

    protected ?string $billToState;

    protected ?string $billToPostCode;

    protected ?string $shipToName;

    protected ?string $shipToContact;

    protected ?string $shipToAddressLine1;

    protected ?string $shipToAddressLine2;

    protected ?string $shipToCity;

    protected ?string $shipToCountry;

    protected ?string $shipToState;

    protected ?string $shipToPostCode;

    protected ?string $sellToAddressLine1;

    protected ?string $sellToAddressLine2;

    protected ?string $sellToCity;

    protected ?string $sellToCountry;

    protected ?string $sellToState;

    protected ?string $sellToPostCode;

    protected ?SalesInvoiceLineCollection $lineItems;

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
     * Executes the get number operation.
     *
     * @internal
     */
    public function getNumber(): ?string
    {
        return $this->number;
    }

    /**
     * Executes the set number operation.
     *
     * @internal
     */
    public function setNumber(?string $number): void
    {
        $this->number = $number;
    }

    /**
     * Executes the get external document number operation.
     *
     * @internal
     */
    public function getExternalDocumentNumber(): ?string
    {
        return $this->externalDocumentNumber;
    }

    /**
     * Executes the set external document number operation.
     *
     * @internal
     */
    public function setExternalDocumentNumber(?string $externalDocumentNumber): void
    {
        $this->externalDocumentNumber = $externalDocumentNumber;
    }

    /**
     * Executes the get invoice date operation.
     *
     * @internal
     */
    public function getInvoiceDate(): ?\DateTimeInterface
    {
        return $this->invoiceDate;
    }

    /**
     * Executes the set invoice date operation.
     *
     * @internal
     */
    public function setInvoiceDate(?\DateTimeInterface $invoiceDate): void
    {
        $this->invoiceDate = $invoiceDate;
    }

    /**
     * Executes the get due date operation.
     *
     * @internal
     */
    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    /**
     * Executes the set due date operation.
     *
     * @internal
     */
    public function setDueDate(?\DateTimeInterface $dueDate): void
    {
        $this->dueDate = $dueDate;
    }

    /**
     * Executes the get customer id operation.
     *
     * @internal
     */
    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    /**
     * Executes the set customer id operation.
     *
     * @internal
     */
    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    /**
     * Executes the get customer operation.
     *
     * @internal
     */
    public function getCustomer(): ?CustomerEntity
    {
        return $this->customer;
    }

    /**
     * Executes the set customer operation.
     *
     * @internal
     */
    public function setCustomer(?CustomerEntity $customer): void
    {
        $this->customer = $customer;
    }

    /**
     * Executes the get order id operation.
     *
     * @internal
     */
    public function getOrderId(): ?string
    {
        return $this->orderId;
    }

    /**
     * Executes the set order id operation.
     *
     * @internal
     */
    public function setOrderId(?string $orderId): void
    {
        $this->orderId = $orderId;
    }

    /**
     * Executes the get order operation.
     *
     * @internal
     */
    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    /**
     * Executes the set order operation.
     *
     * @internal
     */
    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    /**
     * Executes the get currency code operation.
     *
     * @internal
     */
    public function getCurrencyCode(): ?string
    {
        return $this->currencyCode;
    }

    /**
     * Executes the set currency code operation.
     *
     * @internal
     */
    public function setCurrencyCode(?string $currencyCode): void
    {
        $this->currencyCode = $currencyCode;
    }

    /**
     * Executes the get total amount including tax operation.
     *
     * @internal
     */
    public function getTotalAmountIncludingTax(): ?float
    {
        return $this->totalAmountIncludingTax;
    }

    /**
     * Executes the set total amount including tax operation.
     *
     * @internal
     */
    public function setTotalAmountIncludingTax(?float $totalAmountIncludingTax): void
    {
        $this->totalAmountIncludingTax = $totalAmountIncludingTax;
    }

    /**
     * Executes the get discount amount operation.
     *
     * @internal
     */
    public function getDiscountAmount(): ?float
    {
        return $this->discountAmount;
    }

    /**
     * Executes the set discount amount operation.
     *
     * @internal
     */
    public function setDiscountAmount(?float $discountAmount): void
    {
        $this->discountAmount = $discountAmount;
    }

    /**
     * Executes the get remaining amount operation.
     *
     * @internal
     */
    public function getRemainingAmount(): ?float
    {
        return $this->remainingAmount;
    }

    /**
     * Executes the set remaining amount operation.
     *
     * @internal
     */
    public function setRemainingAmount(?float $remainingAmount): void
    {
        $this->remainingAmount = $remainingAmount;
    }

    /**
     * Executes the get salesperson operation.
     *
     * @internal
     */
    public function getSalesperson(): ?string
    {
        return $this->salesperson;
    }

    /**
     * Executes the set salesperson operation.
     *
     * @internal
     */
    public function setSalesperson(?string $salesperson): void
    {
        $this->salesperson = $salesperson;
    }

    /**
     * Executes the get payment terms id operation.
     *
     * @internal
     */
    public function getPaymentTermsId(): ?string
    {
        return $this->paymentTermsId;
    }

    /**
     * Executes the set payment terms id operation.
     *
     * @internal
     */
    public function setPaymentTermsId(?string $paymentTermsId): void
    {
        $this->paymentTermsId = $paymentTermsId;
    }

    /**
     * Executes the get shipment method id operation.
     *
     * @internal
     */
    public function getShipmentMethodId(): ?string
    {
        return $this->shipmentMethodId;
    }

    /**
     * Executes the set shipment method id operation.
     *
     * @internal
     */
    public function setShipmentMethodId(?string $shipmentMethodId): void
    {
        $this->shipmentMethodId = $shipmentMethodId;
    }

    /**
     * Executes the get status operation.
     *
     * @internal
     */
    public function getStatus(): ?string
    {
        return $this->status;
    }

    /**
     * Executes the set status operation.
     *
     * @internal
     */
    public function setStatus(?string $status): void
    {
        $this->status = $status;
    }

    /**
     * Executes the get bill to name operation.
     *
     * @internal
     */
    public function getBillToName(): ?string
    {
        return $this->billToName;
    }

    /**
     * Executes the set bill to name operation.
     *
     * @internal
     */
    public function setBillToName(?string $billToName): void
    {
        $this->billToName = $billToName;
    }

    /**
     * Executes the get bill to address line1 operation.
     *
     * @internal
     */
    public function getBillToAddressLine1(): ?string
    {
        return $this->billToAddressLine1;
    }

    /**
     * Executes the set bill to address line1 operation.
     *
     * @internal
     */
    public function setBillToAddressLine1(?string $billToAddressLine1): void
    {
        $this->billToAddressLine1 = $billToAddressLine1;
    }

    /**
     * Executes the get bill to address line2 operation.
     *
     * @internal
     */
    public function getBillToAddressLine2(): ?string
    {
        return $this->billToAddressLine2;
    }

    /**
     * Executes the set bill to address line2 operation.
     *
     * @internal
     */
    public function setBillToAddressLine2(?string $billToAddressLine2): void
    {
        $this->billToAddressLine2 = $billToAddressLine2;
    }

    /**
     * Executes the get bill to city operation.
     *
     * @internal
     */
    public function getBillToCity(): ?string
    {
        return $this->billToCity;
    }

    /**
     * Executes the set bill to city operation.
     *
     * @internal
     */
    public function setBillToCity(?string $billToCity): void
    {
        $this->billToCity = $billToCity;
    }

    /**
     * Executes the get bill to country operation.
     *
     * @internal
     */
    public function getBillToCountry(): ?string
    {
        return $this->billToCountry;
    }

    /**
     * Executes the set bill to country operation.
     *
     * @internal
     */
    public function setBillToCountry(?string $billToCountry): void
    {
        $this->billToCountry = $billToCountry;
    }

    /**
     * Executes the get bill to state operation.
     *
     * @internal
     */
    public function getBillToState(): ?string
    {
        return $this->billToState;
    }

    /**
     * Executes the set bill to state operation.
     *
     * @internal
     */
    public function setBillToState(?string $billToState): void
    {
        $this->billToState = $billToState;
    }

    /**
     * Executes the get bill to post code operation.
     *
     * @internal
     */
    public function getBillToPostCode(): ?string
    {
        return $this->billToPostCode;
    }

    /**
     * Executes the set bill to post code operation.
     *
     * @internal
     */
    public function setBillToPostCode(?string $billToPostCode): void
    {
        $this->billToPostCode = $billToPostCode;
    }

    /**
     * Executes the get ship to name operation.
     *
     * @internal
     */
    public function getShipToName(): ?string
    {
        return $this->shipToName;
    }

    /**
     * Executes the set ship to name operation.
     *
     * @internal
     */
    public function setShipToName(?string $shipToName): void
    {
        $this->shipToName = $shipToName;
    }

    /**
     * Executes the get ship to contact operation.
     *
     * @internal
     */
    public function getShipToContact(): ?string
    {
        return $this->shipToContact;
    }

    /**
     * Executes the set ship to contact operation.
     *
     * @internal
     */
    public function setShipToContact(?string $shipToContact): void
    {
        $this->shipToContact = $shipToContact;
    }

    /**
     * Executes the get ship to address line1 operation.
     *
     * @internal
     */
    public function getShipToAddressLine1(): ?string
    {
        return $this->shipToAddressLine1;
    }

    /**
     * Executes the set ship to address line1 operation.
     *
     * @internal
     */
    public function setShipToAddressLine1(?string $shipToAddressLine1): void
    {
        $this->shipToAddressLine1 = $shipToAddressLine1;
    }

    /**
     * Executes the get ship to address line2 operation.
     *
     * @internal
     */
    public function getShipToAddressLine2(): ?string
    {
        return $this->shipToAddressLine2;
    }

    /**
     * Executes the set ship to address line2 operation.
     *
     * @internal
     */
    public function setShipToAddressLine2(?string $shipToAddressLine2): void
    {
        $this->shipToAddressLine2 = $shipToAddressLine2;
    }

    /**
     * Executes the get ship to city operation.
     *
     * @internal
     */
    public function getShipToCity(): ?string
    {
        return $this->shipToCity;
    }

    /**
     * Executes the set ship to city operation.
     *
     * @internal
     */
    public function setShipToCity(?string $shipToCity): void
    {
        $this->shipToCity = $shipToCity;
    }

    /**
     * Executes the get ship to country operation.
     *
     * @internal
     */
    public function getShipToCountry(): ?string
    {
        return $this->shipToCountry;
    }

    /**
     * Executes the set ship to country operation.
     *
     * @internal
     */
    public function setShipToCountry(?string $shipToCountry): void
    {
        $this->shipToCountry = $shipToCountry;
    }

    /**
     * Executes the get ship to state operation.
     *
     * @internal
     */
    public function getShipToState(): ?string
    {
        return $this->shipToState;
    }

    /**
     * Executes the set ship to state operation.
     *
     * @internal
     */
    public function setShipToState(?string $shipToState): void
    {
        $this->shipToState = $shipToState;
    }

    /**
     * Executes the get ship to post code operation.
     *
     * @internal
     */
    public function getShipToPostCode(): ?string
    {
        return $this->shipToPostCode;
    }

    /**
     * Executes the set ship to post code operation.
     *
     * @internal
     */
    public function setShipToPostCode(?string $shipToPostCode): void
    {
        $this->shipToPostCode = $shipToPostCode;
    }

    /**
     * Executes the get sell to address line1 operation.
     *
     * @internal
     */
    public function getSellToAddressLine1(): ?string
    {
        return $this->sellToAddressLine1;
    }

    /**
     * Executes the set sell to address line1 operation.
     *
     * @internal
     */
    public function setSellToAddressLine1(?string $sellToAddressLine1): void
    {
        $this->sellToAddressLine1 = $sellToAddressLine1;
    }

    /**
     * Executes the get sell to address line2 operation.
     *
     * @internal
     */
    public function getSellToAddressLine2(): ?string
    {
        return $this->sellToAddressLine2;
    }

    /**
     * Executes the set sell to address line2 operation.
     *
     * @internal
     */
    public function setSellToAddressLine2(?string $sellToAddressLine2): void
    {
        $this->sellToAddressLine2 = $sellToAddressLine2;
    }

    /**
     * Executes the get sell to city operation.
     *
     * @internal
     */
    public function getSellToCity(): ?string
    {
        return $this->sellToCity;
    }

    /**
     * Executes the set sell to city operation.
     *
     * @internal
     */
    public function setSellToCity(?string $sellToCity): void
    {
        $this->sellToCity = $sellToCity;
    }

    /**
     * Executes the get sell to country operation.
     *
     * @internal
     */
    public function getSellToCountry(): ?string
    {
        return $this->sellToCountry;
    }

    /**
     * Executes the set sell to country operation.
     *
     * @internal
     */
    public function setSellToCountry(?string $sellToCountry): void
    {
        $this->sellToCountry = $sellToCountry;
    }

    /**
     * Executes the get sell to state operation.
     *
     * @internal
     */
    public function getSellToState(): ?string
    {
        return $this->sellToState;
    }

    /**
     * Executes the set sell to state operation.
     *
     * @internal
     */
    public function setSellToState(?string $sellToState): void
    {
        $this->sellToState = $sellToState;
    }

    /**
     * Executes the get sell to post code operation.
     *
     * @internal
     */
    public function getSellToPostCode(): ?string
    {
        return $this->sellToPostCode;
    }

    /**
     * Executes the set sell to post code operation.
     *
     * @internal
     */
    public function setSellToPostCode(?string $sellToPostCode): void
    {
        $this->sellToPostCode = $sellToPostCode;
    }

    /**
     * Executes the get line items operation.
     *
     * @internal
     */
    public function getLineItems(): ?SalesInvoiceLineCollection
    {
        return $this->lineItems;
    }

    /**
     * Executes the set line items operation.
     *
     * @internal
     */
    public function setLineItems(?SalesInvoiceLineCollection $lineItems): void
    {
        $this->lineItems = $lineItems;
    }

}
