<?php declare(strict_types=1);

namespace DolphinForceResetPassword\Subscriber;

use DolphinForceResetPassword\DolphinForceResetPassword;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\RouterInterface;

class CustomerLoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RouterInterface $router,
        private readonly RequestStack $requestStack
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            CustomerLoginEvent::class => 'onCustomerLogin',
        ];
    }

    public function onCustomerLogin(CustomerLoginEvent $event): void
    {
        $customer = $event->getCustomer();
        $customFields = $customer->getCustomFields() ?? [];

        $forceReset = $customFields[DolphinForceResetPassword::CUSTOM_FIELD_NAME] ?? false;

        // Check if the forced reset flag is active
        if (!$forceReset) {
            return;
        }

        // Invalidate current session/logout
        $request = $this->requestStack->getCurrentRequest();
        if ($request && $request->hasSession()) {
            $session = $request->getSession();
            $session->invalidate();

            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add(
                    'danger',
                    'Your password must be updated before logging in. Please reset your password below.'
                );
            }
            $session->save();
        }

        // Redirect to password recovery page
        $redirectUrl = $this->router->generate('frontend.account.recover.page');
        
        // Prevent default login workflow by throwing a redirect exception or redirecting
        header('Location: ' . $redirectUrl);
        exit;
    }
}