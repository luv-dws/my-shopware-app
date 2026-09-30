<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Subscriber;

use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderMessage;
use Netformic\MicrosoftBCIntegration\Service\OrderSyncService;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\Event\StateMachineStateChangeEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Listens for Shopware order placement and transaction state events to persist BC integration
 * custom fields and dispatch synchronization messages.
 */
class OrderSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly MessageBusInterface $messageBus,
        private readonly OrderSyncService $orderSyncService
    ) {
    }

    /**
     * Registers the events handled by this subscriber.
     */
    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onCheckoutOrderPlaced',
            'state_machine.order_transaction.state_changed' => 'onTransactionStateChanged',
        ];
    }

    /**
     * Updates order custom fields upon placement and dispatches a background synchronization message.
     *
     * @param CheckoutOrderPlacedEvent $event
     * @return void
     */
    public function onCheckoutOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $order = $event->getOrder();
        $context = $event->getContext();

        $this->updateOrderCustomFields($order, $context);

        // Dispatch the order ID to the message queue for background processing
        $this->messageBus->dispatch(new SyncOrderMessage($order->getId()));
    }

    /**
     * Listens for transaction state changes (e.g. payment completed/authorized) and links the transaction to BC.
     *
     * @param StateMachineStateChangeEvent $event
     * @return void
     */
    public function onTransactionStateChanged(StateMachineStateChangeEvent $event): void
    {
        if ($event->getTransitionSide() != StateMachineStateChangeEvent::STATE_MACHINE_TRANSITION_SIDE_ENTER) {
            return;
        }

        $nextState = $event->getNextState()->getTechnicalName();
        if (!in_array($nextState, [OrderTransactionStates::STATE_AUTHORIZED,OrderTransactionStates::STATE_PAID])) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('transactions.id', $event->getTransition()->getEntityId()));
        $criteria->addAssociation('transactions');

        $order = $this->orderRepository->search($criteria, $event->getContext())->first();
        if (!$order) {
            return;
        }

        $this->orderSyncService->syncPayFabricTransaction($order, $event->getContext());
    }

    /**
     * Extracts shipping address metadata and serialized product rule matches
     * to update Microsoft Business Central custom fields on the order entity.
     *
     * @param OrderEntity $order
     * @param Context $context
     * @return void
     */
    private function updateOrderCustomFields(OrderEntity $order, Context $context): void
    {
        // Resolve shipping address custom fields
        $shippingAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress() ?: $order->getBillingAddress();
        $shippingCustomFields = $shippingAddress?->getCustomFields() ?? [];

        // Parse FFL Expiry
        $fflExpiry = $shippingCustomFields[PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_EXPIRY->value] ?? null;

        // Parse FFL Document Code
        $fflDocumentCode = $shippingCustomFields[PluginConfig::CUSTOM_FIELD_ADDRESS_FFL_DOCUMENT_CODE->value] ?? null;

        // Check if FFL is required using configured serialized-product Cart Rule
        $ruleId = $this->systemConfigService->get(
            PluginConfig::CONFIG_CART_CONTAINS_SERIALIZED_RULE_ID->value,
            $order->getSalesChannelId()
        );
        $isFflRequired = $ruleId && in_array($ruleId, $order->getRuleIds() ?? [], true);

        // Update custom fields on the order
        $this->orderRepository->update([
            [
                'id' => $order->getId(),
                'customFields' => [
                    PluginConfig::CUSTOM_FIELD_FFL_EXPIRY->value => $fflExpiry,
                    PluginConfig::CUSTOM_FIELD_FFL_DOCUMENT_CODE->value => $fflDocumentCode ?: null,
                    PluginConfig::CUSTOM_FIELD_FFL_REQUIRED->value => $isFflRequired,
                ],
            ],
        ], $context);
    }
}
