<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Controller\Api;

use Netformic\MicrosoftBCIntegration\Service\OrderSyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles synchronous API requests from the Shopware Administration
 * for manual synchronization of orders to Microsoft Business Central.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('checkout')]
class OrderSyncController extends AbstractController
{
    private OrderSyncService $orderSyncService;

    /**
     * @internal
     */
    public function __construct(OrderSyncService $orderSyncService)
    {
        $this->orderSyncService = $orderSyncService;
    }

    /**
     * Manually triggers the synchronization of a specific order to Business Central.
     * 
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     */
    #[Route(path: '/api/_action/netformic-bc/sync-order', name: 'api.action.netformic_bc.sync_order', methods: ['POST'])]
    public function syncOrder(Request $request, Context $context): JsonResponse
    {
        $orderId = $request->request->get('orderId');

        if (!$orderId) {
            return new JsonResponse(['success' => false, 'error' => 'Missing orderId parameter.'], 400);
        }

        $result = $this->orderSyncService->syncOrder($orderId, $context, true);

        if (!$result['success']) {
            return new JsonResponse($result, 400);
        }

        return new JsonResponse($result);
    }
}
