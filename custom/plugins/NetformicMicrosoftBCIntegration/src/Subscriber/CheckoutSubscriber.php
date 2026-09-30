<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Subscriber;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Netformic\MicrosoftBCIntegration\Service\ProductCacheService;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Subscriber for cart and checkout events.
 * Handles dynamic ERP price and inventory injection, as well as FFL requirements validation.
 */
class CheckoutSubscriber implements EventSubscriberInterface
{

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $productRepository,
        private readonly TranslatorInterface $translator,
        private readonly ProductCacheService $productService,
        private readonly CartService $cartService,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $ruleRepository
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutConfirmPageLoadedEvent::class => 'onCheckoutConfirmPageLoaded',
            CheckoutCartPageLoadedEvent::class => 'onCheckoutCartPageLoaded',
            OffcanvasCartPageLoadedEvent::class => 'onOffcanvasCartPageLoaded',
        ];
    }

    /**
     * Event listener for the offcanvas cart page loaded event.
     *
     * @param OffcanvasCartPageLoadedEvent $event
     */
    public function onOffcanvasCartPageLoaded(OffcanvasCartPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        if ($page->getCart() && ($productLineItems = $this->getProductLineItems($page->getCart()))) {
            $this->syncErpDataForCart($productLineItems, $event, $page);
        }
    }

    /**
     * Event listener for the checkout cart page loaded event.
     *
     * @param CheckoutCartPageLoadedEvent $event
     */
    public function onCheckoutCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        if ($page->getCart() && ($productLineItems = $this->getProductLineItems($page->getCart()))) {
            $this->syncErpDataForCart($productLineItems, $event, $page);
        }
    }

    /**
     * Event listener for the checkout confirm page loaded event.
     *
     * @param CheckoutConfirmPageLoadedEvent $event
     */
    public function onCheckoutConfirmPageLoaded(CheckoutConfirmPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $cart = $page->getCart();

        if (!$cart) {
            return;
        }

        // Check FFL requirement and warn if shipping address lacks a valid FFL
        $this->validateFflRequirement($cart, $event);

        if ($productLineItems = $this->getProductLineItems($cart)) {
            // Apply real-time ERP pricing and inventory limits before confirming the order
            $this->syncErpDataForCart($productLineItems, $event, $page);
        }
    }

    /**
     * Validates if any product in the cart requires an FFL and if the customer has a valid FFL.
     *
     * @param Cart $cart
     * @param CheckoutConfirmPageLoadedEvent $event
     */
    private function validateFflRequirement(Cart $cart, CheckoutConfirmPageLoadedEvent $event): void
    {
        // Skip validation entirely if no items in the cart require an FFL check
        if (!$this->isFflRequired($cart, $event->getSalesChannelContext())) {
            return;
        }

        $customer = $event->getSalesChannelContext()->getCustomer();
        if (!$customer) {
            return;
        }

        // Ensure the customer has selected an active shipping address
        $shippingAddress = $customer->getActiveShippingAddress();

        // Skip flash warning if an address is missing or it already has a valid FFL
        if (!$shippingAddress || $this->hasValidFfl($cart, $event->getSalesChannelContext())) {
            return;
        }

        // Address lacks a valid FFL - display a warning flash message to the user
        $session = $event->getRequest()->getSession();
        if ($session && method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add(
                'warning',
                $this->translator->trans(PluginConfig::SNIPPET_FFL_WARNING->value)
            );
        }
    }

    /**
     * Extracts and maps all product line items from the cart in one pass.
     *
     * @param Cart $cart
     * @return array<string, LineItem> Map of productId -> LineItem
     */
    private function getProductLineItems(Cart $cart): array
    {
        $productLineItems = [];
        foreach ($cart->getLineItems()->getFlat() as $lineItem) {
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE && $lineItem->getReferencedId()) {
                $productLineItems[$lineItem->getReferencedId()] = $lineItem;
            }
        }

        return $productLineItems;
    }

    /**
     * Syncs ERP data for all product line items in the cart and applies live
     * price and stock values to the current request immediately.
     *
     * @param array  $productLineItems Map of productId -> LineItem
     * @param object $event            Any page-loaded event with getSalesChannelContext()
     * @param mixed  $page             Page object
     */
    private function syncErpDataForCart(array $productLineItems, $event, $page): void
    {
        $salesChannelContext = $event->getSalesChannelContext();

        // Fetch product entities to resolve their BC System IDs
        $products = $this->productRepository->search(
            new Criteria(array_keys($productLineItems)),
            $salesChannelContext->getContext()
        );

        if ($products->count() === 0) {
            return;
        }

        // Only sync if there are uncached products.
        $uncachedProducts = $this->productService->getUncachedProducts($products->getElements());
        if (empty($uncachedProducts)) {
            return;
        }

        // Sync - check Redis, fetch missing from API, upsert to DB, cache in Redis
        $erpProducts = $this->productService->syncProductErpData(
            $uncachedProducts,
            $salesChannelContext->getContext()
        );

        // Build systemId -> LineItem map directly for the uncached items.
        $lineItemsBySystemId = [];
        foreach ($uncachedProducts as $systemId => $product) {
            $lineItemsBySystemId[(string) $systemId] = $productLineItems[$product->getId()];
        }

        if (empty($lineItemsBySystemId) || empty($erpProducts)) {
            return;
        }

        $needsRecalculation = false;

        foreach ($erpProducts as $erpProduct) {
            $systemId = (string) ($erpProduct['systemId'] ?? $erpProduct['id'] ?? '');
            $lineItem = $lineItemsBySystemId[$systemId] ?? null;

            if (!$lineItem) {
                continue;
            }

            // Enforce live stock limits on the line item quantity information
            if (isset($erpProduct['inventory'])) {
                $inventory = (int) $erpProduct['inventory'];

                if (($qtyInfo = $lineItem->getQuantityInformation()) && $qtyInfo->getMaxPurchase() !== max(0, $inventory)) {
                    $qtyInfo->setMaxPurchase(max(0, $inventory));
                        $needsRecalculation = true;
                }

                // Clamp cart quantity to available stock
                if ($lineItem->getQuantity() > $inventory) {
                    $lineItem->setQuantity(max(1, $inventory));
                    if ($inventory <= 0 && ($delivery = $lineItem->getDeliveryInformation()) !== null) {
                            $delivery->setStock(0);
                    }
                    $needsRecalculation = true;
                }
            }

            // Override the line item price definition with the live ERP unit price
            $unitPrice = isset($erpProduct['unitPrice']) ? (float) $erpProduct['unitPrice'] : null;
            if ($unitPrice === null || $unitPrice <= 0.0) {
                continue;
            }

            if ($lineItem->getPrice() !== null && abs($lineItem->getPrice()->getUnitPrice() - $unitPrice) > 0.01) {
                $priceDefinition = $lineItem->getPriceDefinition();
                if ($priceDefinition instanceof QuantityPriceDefinition) {
                    $lineItem->setPriceDefinition(new QuantityPriceDefinition(
                        $unitPrice,
                        $priceDefinition->getTaxRules(),
                        $priceDefinition->getQuantity()
                    ));
                    $needsRecalculation = true;
                }
            }
        }

        // Trigger a full cart recalculation so taxes and totals are correct
        if ($needsRecalculation) {
            $page->setCart($this->cartService->recalculate($page->getCart(), $salesChannelContext));
        }
    }

    /**
     * Determines whether any product in the cart requires an FFL check.
     *
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return bool
     */
    private function isFflRequired(Cart $cart, SalesChannelContext $context): bool
    {
        $ruleId = $this->systemConfigService->get(
            PluginConfig::CONFIG_CART_CONTAINS_SERIALIZED_RULE_ID->value,
            $context->getSalesChannel()->getId()
        );
        if (!$ruleId) {
            return false;
        }

        /** @var Rule|null $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $context->getContext())->first();
        $payload = $rule?->getPayload();
        if (!$payload instanceof Rule) {
            return false;
        }

        return $payload->match(new CartRuleScope($cart, $context));
    }

    /**
     * Checks if the cart matches the FFL validation rule configured in system config.
     *
     * @param Cart $cart
     * @param SalesChannelContext $context
     * @return bool
     */
    public function hasValidFfl(Cart $cart, SalesChannelContext $context): bool
    {
        $ruleId = $this->systemConfigService->get(
            PluginConfig::CONFIG_FFL_VALIDATION_RULE_ID->value,
            $context->getSalesChannel()->getId()
        );
        if (!$ruleId) {
            return false;
        }

        /** @var Rule|null $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $context->getContext())->first();
        $payload = $rule?->getPayload();
        if (!$payload instanceof Rule) {
            return false;
        }

        return $payload->match(new CartRuleScope($cart, $context));
    }
}
