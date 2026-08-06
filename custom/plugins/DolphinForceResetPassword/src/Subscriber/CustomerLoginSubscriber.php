<?php declare(strict_types=1);

namespace DolphinForceResetPassword\Subscriber;

use DolphinForceResetPassword\DolphinForceResetPassword;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Customer\Event\CustomerBeforeLoginEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityRepository $customerRepository
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerBeforeLoginEvent::class => 'onCustomerBeforeLogin',
        ];
    }

    public function onCustomerBeforeLogin(CustomerBeforeLoginEvent $event): void
    {
        $email = $event->getEmail();
        if ($email === '') {
            return;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('email', $email));

        /** @var CustomerEntity|null $customer */
        $customer = $this->customerRepository->search($criteria, $event->getContext())->first();

        if ($customer === null) {
            return;
        }

        $customFields = $customer->getCustomFields() ?? [];
        $forceReset = $customFields[DolphinForceResetPassword::CUSTOM_FIELD_NAME] ?? false;

        // Check if the forced reset flag is active
        if ($forceReset) {
            throw CustomerException::passwordPoliciesUpdated();
        }
    }
}