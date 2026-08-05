<?php declare(strict_types=1);

namespace DolphinForceResetPassword\Subscriber;

use DolphinForceResetPassword\DolphinForceResetPassword;
use Shopware\Core\Checkout\Customer\CustomerEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordResetSubscriber implements EventSubscriberInterface
{
    /**
     * Prevents infinite recursive updates when this subscriber updates the customer entity.
     */
    private bool $isUpdating = false;

    public function __construct(
        private readonly EntityRepository $customerRepository
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Listening to the DAL written event covers storefront resets, admin password changes, and API updates.
            CustomerEvents::CUSTOMER_WRITTEN_EVENT => 'onCustomerWritten',
        ];
    }

    public function onCustomerWritten(EntityWrittenEvent $event): void
    {
        if ($this->isUpdating) {
            return;
        }

        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();

            // Ignore if the operation is explicitly enabling the force reset flag
            if (
                isset($payload['customFields'][DolphinForceResetPassword::CUSTOM_FIELD_NAME])
                && $payload['customFields'][DolphinForceResetPassword::CUSTOM_FIELD_NAME] === true
            ) {
                continue;
            }

            // Check if a new password string was saved in this write
            if (isset($payload['password']) && \is_string($payload['password']) && $payload['password'] !== '') {
                $primaryKey = $writeResult->getPrimaryKey();
                $customerId = \is_array($primaryKey) ? ($primaryKey['id'] ?? null) : $primaryKey;

                if ($customerId && \is_string($customerId)) {
                    $this->clearForceResetFlag($customerId, $event->getContext());
                }
            }
        }
    }

    private function clearForceResetFlag(string $customerId, Context $context): void
    {
        $this->isUpdating = true;

        try {
            // Direct write: Shopware merges customFields key-by-key, no prior DB search needed.
            $this->customerRepository->update([
                [
                    'id' => $customerId,
                    'customFields' => [
                        DolphinForceResetPassword::CUSTOM_FIELD_NAME => false,
                    ],
                ],
            ], $context);
        } finally {
            $this->isUpdating = false;
        }
    }
}