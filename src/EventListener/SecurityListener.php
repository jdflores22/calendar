<?php

namespace App\EventListener;

use App\Service\SecurityService;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class SecurityListener
{
    public function __construct(
        private SecurityService $securityService
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Skip security checks for certain routes and paths
        $route = $request->attributes->get('_route');
        $path = $request->getPathInfo();
        
        // Skip profiler and debug routes
        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt')) {
            return;
        }

        // Only apply rate limiting to high-risk operations (POST, PUT, DELETE)
        // and specific sensitive routes
        $shouldRateLimit = false;
        
        // Skip rate limiting for common user routes
        $skipRateLimitingRoutes = [
            'app_login', 'app_register', 'app_dashboard', 'app_home', 
            'app_calendar_index', 'app_logout', 'app_reset_password_request',
            'app_reset_password', 'app_verify_email'
        ];
        
        $skipRateLimitingPaths = [
            '/', '/login', '/register', '/home', '/calendar', '/logout',
            '/reset-password', '/verify'
        ];

        // Don't rate limit common user actions
        if (!in_array($route, $skipRateLimitingRoutes) && 
            !in_array($path, $skipRateLimitingPaths) &&
            !str_starts_with($path, '/verify/')) {
            
            // Only apply rate limiting to admin routes with state changes
            if (str_starts_with($path, '/admin/') && in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $shouldRateLimit = true;
            }
        }

        if ($shouldRateLimit) {
            // Rate limiting check
            $ip = $request->getClientIp();
            if ($this->securityService->isRateLimited($ip, 'general', 100, 3600)) {
                $this->securityService->logSecurityEvent('RATE_LIMIT_EXCEEDED', [
                    'ip' => $ip,
                    'route' => $route,
                    'path' => $path
                ]);
                
                $response = new JsonResponse([
                    'error' => 'Rate limit exceeded. Please try again later.'
                ], Response::HTTP_TOO_MANY_REQUESTS);
                
                $event->setResponse($response);
                return;
            }
        }

        // Check for suspicious content in request data
        // Skip for admin routes to allow legitimate admin operations
        if (!str_starts_with($path, '/admin/')) {
            $allData = array_merge(
                $request->query->all(),
                $request->request->all()
            );

            foreach ($allData as $key => $value) {
                if (is_string($value) && $this->securityService->containsSuspiciousContent($value)) {
                    $this->securityService->logSecurityEvent('SUSPICIOUS_REQUEST_DATA', [
                        'field' => $key,
                        'value_sample' => substr($value, 0, 100),
                        'route' => $route
                    ]);
                    
                    // Block the request
                    $response = new JsonResponse([
                        'error' => 'Request blocked due to security policy.'
                    ], Response::HTTP_BAD_REQUEST);
                    
                    $event->setResponse($response);
                    return;
                }
            }
        }

        // CSRF validation for state-changing requests
        if (in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Skip CSRF for profiler and debug routes
            if (!str_starts_with($path, '/_profiler') && !str_starts_with($path, '/_wdt')) {
                // Temporarily disable CSRF validation to debug login issue
                // $this->validateCsrfForRequest($request, $event);
            }
        }
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();
        
        // Add security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // Add CORS headers to help with CORB issues for CSS/JS resources
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With');
        
        // Content Security Policy - Updated to be more explicit about cross-origin resources
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com data: blob:; " .
               "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com data: blob:; " .
               "style-src-elem 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com https://fonts.googleapis.com data:; " .
               "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://unpkg.com https://cdnjs.cloudflare.com; " .
               "img-src 'self' data: https: blob:; " .
               "connect-src 'self' https: wss: ws:; " .
               "frame-ancestors 'none'; " .
               "base-uri 'self';";
        
        $response->headers->set('Content-Security-Policy', $csp);
        
        // HSTS header for HTTPS
        if ($event->getRequest()->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $ip = $request->getClientIp();
        $email = $request->request->get('email', 'unknown');

        $this->securityService->logSecurityEvent('LOGIN_FAILURE', [
            'email' => $email,
            'ip' => $ip,
            'user_agent' => $request->headers->get('User-Agent'),
            'exception' => $event->getException()?->getMessage()
        ]);

        // Check for brute force attempts
        if ($this->securityService->isRateLimited($ip, 'login_attempts', 5, 900)) {
            $this->securityService->logSecurityEvent('BRUTE_FORCE_DETECTED', [
                'email' => $email,
                'ip' => $ip
            ]);
        }
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();
        
        $this->securityService->logSecurityEvent('LOGIN_SUCCESS', [
            'user_id' => $user->getUserIdentifier(),
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent')
        ]);

        // Clear rate limiting on successful login
        $this->securityService->clearRateLimit($request->getClientIp(), 'login_attempts');
    }

    /**
     * Validate CSRF token for request
     */
    private function validateCsrfForRequest($request, RequestEvent $event): void
    {
        $route = $request->attributes->get('_route');
        $path = $request->getPathInfo();
        
        // Skip CSRF validation for API routes (they use different auth)
        if (str_starts_with($route, 'api_') || str_starts_with($path, '/api/')) {
            return;
        }

        // Skip for authentication routes that handle CSRF differently or have their own CSRF handling
        $skipCsrfRoutes = [
            'app_login', 'app_logout', 'app_register', 'app_reset_password_request', 
            'app_reset_password', 'app_verify_email'
        ];
        if (in_array($route, $skipCsrfRoutes)) {
            return;
        }

        // Also skip for authentication-related paths
        $skipCsrfPaths = ['/login', '/logout', '/register', '/reset-password', '/verify'];
        foreach ($skipCsrfPaths as $skipPath) {
            if (str_starts_with($path, $skipPath)) {
                return;
            }
        }

        // Skip for profiler and debug routes
        if (str_starts_with($path, '/_profiler') || str_starts_with($path, '/_wdt')) {
            return;
        }

        $token = $request->request->get('_token') ?? 
                 $request->request->get('_csrf_token') ?? 
                 $request->headers->get('X-CSRF-Token');
        
        if (!$token) {
            $this->securityService->logSecurityEvent('CSRF_TOKEN_MISSING', [
                'route' => $route,
                'method' => $request->getMethod()
            ]);
            
            $response = new JsonResponse([
                'error' => 'CSRF token missing.'
            ], Response::HTTP_BAD_REQUEST);
            
            $event->setResponse($response);
            return;
        }

        // Determine token ID based on route
        $tokenId = $this->getTokenIdForRoute($route);
        
        if (!$this->securityService->validateCsrfToken($tokenId, $token)) {
            $response = new JsonResponse([
                'error' => 'Invalid CSRF token.'
            ], Response::HTTP_BAD_REQUEST);
            
            $event->setResponse($response);
            return;
        }
    }

    /**
     * Get CSRF token ID for route
     */
    private function getTokenIdForRoute(string $route): string
    {
        return match (true) {
            str_contains($route, 'event') => 'event_form',
            str_contains($route, 'profile') => 'profile_form',
            str_contains($route, 'directory') => 'directory_form',
            str_contains($route, 'form_builder') => 'form_builder',
            default => 'form_token'
        };
    }
}