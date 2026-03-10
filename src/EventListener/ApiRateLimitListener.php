<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Security;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 10)]
class ApiRateLimitListener
{
    public function __construct(
        private RateLimiterFactory $apiRateLimiter,
        private ?Security $security = null
    ) {}

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Only apply rate limiting to API routes
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Skip rate limiting for main request only
        if (!$event->isMainRequest()) {
            return;
        }

        // Create rate limiter key based on user or IP
        $key = $this->getRateLimitKey($request);
        $limiter = $this->apiRateLimiter->create($key);

        // Check if request is allowed
        $limit = $limiter->consume(1);
        
        if (!$limit->isAccepted()) {
            $response = new JsonResponse([
                'success' => false,
                'message' => 'Rate limit exceeded. Please try again later.',
                'error_code' => 'RATE_LIMIT_EXCEEDED',
                'details' => [
                    'retry_after' => $limit->getRetryAfter()->getTimestamp(),
                    'limit' => $limit->getLimit(),
                    'remaining' => $limit->getRemainingTokens()
                ]
            ], 429);

            // Add rate limit headers
            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
            $response->headers->set('Retry-After', (string) $limit->getRetryAfter()->getTimestamp());

            $event->setResponse($response);
            return;
        }

        // Add rate limit headers to successful requests
        $response = $event->getResponse();
        if ($response) {
            $response->headers->set('X-RateLimit-Limit', (string) $limit->getLimit());
            $response->headers->set('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
            $response->headers->set('X-RateLimit-Reset', (string) $limit->getRetryAfter()->getTimestamp());
        }
    }

    private function getRateLimitKey(\Symfony\Component\HttpFoundation\Request $request): string
    {
        // If user is authenticated, use user ID
        if ($this->security && $this->security->getUser()) {
            return 'api_user_' . $this->security->getUser()->getUserIdentifier();
        }

        // Otherwise, use IP address
        return 'api_ip_' . $request->getClientIp();
    }
}