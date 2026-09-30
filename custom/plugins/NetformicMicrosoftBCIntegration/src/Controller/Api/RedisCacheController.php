<?php

declare(strict_types=1);

namespace Netformic\MicrosoftBCIntegration\Controller\Api;

use Netformic\MicrosoftBCIntegration\Service\ProductCacheService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Framework\Log\Package;

/**
 * Handles API requests from the Shopware Administration
 * for managing the Redis product cache.
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ApiRouteScope::ID]])]
#[Package('checkout')]
class RedisCacheController extends AbstractController
{
    /**
     * @internal
     */
    public function __construct(private readonly ProductCacheService $productService)
    {
    }

    /**
     * Flushes Redis cache entries for one or more specific product system IDs.
     *
     * Accepts the system IDs either as an array or as a comma, semicolon,
     * or newline-separated string.
     *
     * @param Request $request
     * @param Context $context
     *
     * @return JsonResponse
     */
    #[Route(
        path: '/api/_action/netformic-bc/redis-cache/flush',
        name: 'api.action.netformic_bc.redis_cache.flush',
        methods: ['POST']
    )]
    /**
     * Executes the flush operation.
     *
     * @internal
     */
    public function flush(Request $request, Context $context): JsonResponse
    {
        $systemIdsInput = $request->request->get('systemIds', '');

        if (is_array($systemIdsInput)) {
            $systemIds = array_values(array_filter(array_map('trim', $systemIdsInput)));
        } else {
            $systemIds = array_values(
                array_filter(
                    array_map(
                        'trim',
                        preg_split('/[\n,;]+/', (string) $systemIdsInput) ?: []
                    )
                )
            );
        }

        if ($systemIds === []) {
            return new JsonResponse(['error' => 'At least one System ID is required.'], 400);
        }

        $deleted = $this->productService->flushProductCache($systemIds);

        if (!$deleted) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No cache found in redis',
                'deleted' => $deleted,
            ]);
        }

        return new JsonResponse([
            'success' => true,
            'message' => sprintf(
                'Cache entries for %d system ID(s) were flushed.',
                count($systemIds)
            ),
            'deleted' => $deleted,
        ]);
    }

    /**
     * Flushes all product cache entries stored in Redis.
     *
     * @param Context $context
     *
     * @return JsonResponse
     */
    #[Route(
        path: '/api/_action/netformic-bc/redis-cache/flush-all',
        name: 'api.action.netformic_bc.redis_cache.flush_all',
        methods: ['POST']
    )]
    /**
     * Executes the flush all operation.
     *
     * @internal
     */
    public function flushAll(Context $context): JsonResponse
    {
        $deleted = $this->productService->flushProductCache();

        return new JsonResponse([
            'success' => true,
            'message' => 'All product cache entries were flushed.',
            'deleted' => $deleted,
        ]);
    }
}