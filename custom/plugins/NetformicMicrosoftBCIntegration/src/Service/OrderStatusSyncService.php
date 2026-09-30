<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Service;

use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderStatusBatchMessage;
use Netformic\MicrosoftBCIntegration\MessageQueue\Message\SyncOrderStatusMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;
use Netformic\MicrosoftBCIntegration\Core\Content\PluginConfig;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Synchronizes order statuses from Microsoft Business Central.
 */
class OrderStatusSyncService
{
    /**
     * Returns the mapping of Business Central order statuses to Shopware states and actions.
     *
     * @return array<string, array{type: string, state: string, action: string}>
     */
    private function getStatusMapping(): array
    {
        return [
            PluginConfig::BC_STATUS_OPEN->value => [
                'type' => 'order',
                'state' => OrderStates::STATE_OPEN,
                'action' => StateMachineTransitionActions::ACTION_REOPEN,
            ],
            PluginConfig::BC_STATUS_RELEASED->value => [
                'type' => 'order',
                'state' => OrderStates::STATE_IN_PROGRESS,
                'action' => StateMachineTransitionActions::ACTION_PROCESS,
            ],
            PluginConfig::BC_STATUS_PENDING_APPROVAL->value => [
                'type' => 'order',
                'state' => OrderStates::STATE_OPEN,
                'action' => StateMachineTransitionActions::ACTION_REOPEN,
            ],
            PluginConfig::BC_STATUS_PENDING_PREPAYMENT->value => [
                'type' => 'payment',
                'state' => OrderTransactionStates::STATE_FAILED,
                'action' => StateMachineTransitionActions::ACTION_FAIL,
            ],
            PluginConfig::BC_STATUS_AWAITING_PAYMENT_CLEARING->value => [
                'type' => 'payment',
                'state' => OrderTransactionStates::STATE_OPEN,
                'action' => StateMachineTransitionActions::ACTION_REOPEN,
            ],
        ];
    }

    /**
     * Initializes the class dependencies.
     *
     * @internal
     */
    public function __construct(
        private BcApiClient $apiClient,
        private EntityRepository $orderRepository,
        private LoggerInterface $logger,
        private MessageBusInterface $messageBus,
        private SystemConfigService $systemConfigService,
        private EntityRepository $stateMachineStateRepository,
        private StateMachineRegistry $stateMachineRegistry,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Synchronizes order statuses from Business Central in batches.
     * Processes state transitions and manages error logging and fallbacks.
     *
     * @param string|null $nextLink Next page OData link for pagination
     * @param int $take Number of records to sync per batch
     * @param string|null $lastSyncDate Filter for records modified since a certain date
     * @throws \Throwable
     */
    public function syncOrderStatusBatch(?string $nextLink, int $take, ?string $lastSyncDate = null): void
    {
        try {
            // Fetch order records from Business Central API
            $response = $this->apiClient->fetchOrders($nextLink, $take, $lastSyncDate);
            $bcOrders = $response['data'] ?? [];
            $newNextLink = $response['nextLink'] ?? null;

            // Guard clause: stop if no orders were returned
            if (empty($bcOrders)) {
                return;
            }

            // Loop through each order in the batch and dispatch an order status sync message
            foreach ($bcOrders as $bcOrder) {
                $systemId    = $bcOrder['systemId'] ?? null;
                $orderNumber = $bcOrder['number'] ?? null;
                $bcStatus    = $bcOrder['status'] ?? null;

                // Ensure we have a valid system ID and status to work with
                if (!$systemId || !$bcStatus) {
                    continue;
                }

                $this->messageBus->dispatch(
                    new SyncOrderStatusMessage($systemId, $orderNumber, $bcStatus)
                );
            }

            // Dispatch message for the next batch if a new pagination link is present
            if ($newNextLink) {
                $this->messageBus->dispatch(
                    new SyncOrderStatusBatchMessage($newNextLink, $take, $lastSyncDate)
                );
            }
        } catch (\Throwable $e) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderStatusSync.syncExecutionError'), [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'nextLink' => $nextLink,
                'take' => $take,
            ]);
            throw $e;
        }
    }

    /**
     * Synchronizes a single order's status from Business Central.
     *
     * @param string $systemId The Microsoft BC system ID of the order
     * @param string|null $orderNumber The order number
     * @param string $bcStatus The Business Central status string
     */
    public function syncOrderStatus(string $systemId, ?string $orderNumber, string $bcStatus): void
    {
        $context = Context::createDefaultContext();

        try {
            $statusMapping = $this->getStatusMapping();

            // Check if the received status is defined in our mapping
            if (!isset($statusMapping[$bcStatus])) {
                return;
            }

            $mapping = $statusMapping[$bcStatus];

            // Fetch the corresponding Shopware order by BC System ID custom field
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('customFields.' . PluginConfig::CUSTOM_FIELD_ORDER_SYSTEM_ID->value, $systemId));
            $criteria->addAssociation('stateMachineState');
            $criteria->addAssociation('transactions.stateMachineState');

            /** @var OrderEntity|null $order */
            $order = $this->orderRepository->search($criteria, $context)->first();
            if (!$order) {
                $this->logger->info($this->translator->trans('netformic-bc-integration.orderStatusSync.orderNotFoundInShopware'), [
                    'systemId'    => $systemId,
                    'orderNumber' => $orderNumber,
                ]);
                return;
            }

            if ($mapping['type'] == 'order') {
                $this->processOrderTransition($order, $orderNumber, $mapping, $context);
            } elseif ($mapping['type'] == 'payment') {
                $this->processPaymentTransition($order, $orderNumber, $mapping, $context);
            }
        } catch (\Throwable $bcOrderEx) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderStatusSync.orderSyncError'), [
                'systemId'    => $systemId,
                'orderNumber' => $orderNumber,
                'error'       => $bcOrderEx->getMessage(),
            ]);
        }
    }

    /**
     * Processes order-level state transitions and handles direct update fallback.
     *
     * @param OrderEntity $order
     * @param string|null $orderNumber
     * @param array{type: string, state: string, action: string} $mapping
     * @param Context $context
     */
    private function processOrderTransition(OrderEntity $order, ?string $orderNumber, array $mapping, Context $context): void
    {
        $currentState = $order->getStateMachineState();
        $currentStateTechnicalName = $currentState?->getTechnicalName();

        if ($currentStateTechnicalName == $mapping['state']) {
            return;
        }

        try {
            $this->stateMachineRegistry->transition(
                new Transition('order', $order->getId(), $mapping['action'], 'stateId'),
                $context
            );
            return;
        } catch (\Throwable $e) {
            $this->logger->warning($this->translator->trans('netformic-bc-integration.orderStatusSync.transitionFailedFallback'), [
                'orderId'     => $order->getId(),
                'orderNumber' => $orderNumber,
                'fromState'   => $currentStateTechnicalName,
                'toState'     => $mapping['state'],
                'action'      => $mapping['action'],
                'error'       => $e->getMessage(),
            ]);
        }

        // Fallback: Force update the stateId
        $stateId = $this->getStateMachineStateId('order.state', $mapping['state'], $context);
        if (!$stateId) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderStatusSync.stateIdNotFound'), [
                'stateName' => $mapping['state'],
            ]);
            return;
        }

        try {
            $this->orderRepository->update([
                [
                    'id'      => $order->getId(),
                    'stateId' => $stateId,
                ],
            ], $context);
        } catch (\Throwable $directUpdateEx) {
            $this->logger->error($this->translator->trans('netformic-bc-integration.orderStatusSync.directUpdateFailed'), [
                'orderId'     => $order->getId(),
                'orderNumber' => $orderNumber,
                'fromState'   => $currentStateTechnicalName,
                'toState'     => $mapping['state'],
                'error'       => $directUpdateEx->getMessage(),
            ]);
        }
    }

    /**
     * Processes payment transaction-level state transitions.
     *
     * @param OrderEntity $order
     * @param string|null $orderNumber
     * @param array{type: string, state: string, action: string} $mapping
     * @param Context $context
     */
    private function processPaymentTransition(OrderEntity $order, ?string $orderNumber, array $mapping, Context $context): void
    {
        $transactions = $order->getTransactions();
        if (!$transactions) {
            return;
        }

        /** @var OrderTransactionEntity $transaction */
        foreach ($transactions as $transaction) {
            $currentState = $transaction->getStateMachineState();
            $currentStateTechnicalName = $currentState?->getTechnicalName();

            if ($currentStateTechnicalName == $mapping['state']) {
                continue;
            }

            try {
                $this->stateMachineRegistry->transition(
                    new Transition('order_transaction', $transaction->getId(), $mapping['action'], 'stateId'),
                    $context
                );
            } catch (\Throwable $e) {
                $this->logger->warning($this->translator->trans('netformic-bc-integration.orderStatusSync.transactionTransitionFailed'), [
                    'orderId'       => $order->getId(),
                    'orderNumber'   => $orderNumber,
                    'transactionId' => $transaction->getId(),
                    'fromState'     => $currentStateTechnicalName,
                    'toState'       => $mapping['state'],
                    'action'        => $mapping['action'],
                    'error'         => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Helper to retrieve a state machine state ID by state machine and state name.
     *
     * @param string $stateMachineName Technical name of the state machine
     * @param string $stateName Technical name of the state
     * @param Context $context Shopware Context object
     * @return string|null The State UUID if found, null otherwise
     */
    private function getStateMachineStateId(string $stateMachineName, string $stateName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stateMachine.technicalName', $stateMachineName));
        $criteria->addFilter(new EqualsFilter('technicalName', $stateName));
        return $this->stateMachineStateRepository->searchIds($criteria, $context)->firstId();
    }
}
