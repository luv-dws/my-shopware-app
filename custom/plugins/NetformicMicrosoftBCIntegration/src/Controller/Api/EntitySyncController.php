<?php declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Controller\Api;

use Netformic\MicrosoftBCIntegration\Service\CustomerSyncService;
use Netformic\MicrosoftBCIntegration\Service\ProductSyncService;
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
 * for manual synchronization of customers and products from Microsoft Business Central.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('checkout')]
class EntitySyncController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CustomerSyncService $customerSyncService,
        private readonly ProductSyncService $productSyncService
    ) {
    }

    /**
     * Manually triggers the synchronization of a specific customer from Business Central by systemId.
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     */
    #[Route(path: '/api/_action/netformic-bc/sync-customer', name: 'api.action.netformic_bc.sync_customer', methods: ['POST'])]
    public function syncCustomer(Request $request, Context $context): JsonResponse
    {
        $systemId = $request->request->get('systemId');

        if (!$systemId) {
            return new JsonResponse(['success' => false, 'error' => 'Missing systemId parameter.'], 400);
        }

        try {
            $this->customerSyncService->syncCustomer($systemId, $context);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Manually triggers the synchronization of a specific product from Business Central by systemId.
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     */
    #[Route(path: '/api/_action/netformic-bc/sync-product', name: 'api.action.netformic_bc.sync_product', methods: ['POST'])]
    public function syncProduct(Request $request, Context $context): JsonResponse
    {
        $systemId = $request->request->get('systemId');

        if (!$systemId) {
            return new JsonResponse(['success' => false, 'error' => 'Missing systemId parameter.'], 400);
        }

        try {
            $this->productSyncService->syncProduct($systemId, $context);

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
