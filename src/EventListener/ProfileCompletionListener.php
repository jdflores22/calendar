<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
class ProfileCompletionListener
{
    private const ALLOWED_ROUTES = [
        'app_profile_complete',
        'app_profile_edit',
        'app_logout',
        '_wdt',
        '_profiler',
        '_profiler_search',
        '_profiler_search_bar',
        '_profiler_search_results',
        '_profiler_router',
        '_profiler_exception',
        '_profiler_exception_css',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private RouterInterface $router
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        // Only process main requests
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        
        // Force log to file
        file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
            date('Y-m-d H:i:s') . " - Route: $route\n", FILE_APPEND);

        // Skip if no route or if it's an allowed route
        if (!$route || in_array($route, self::ALLOWED_ROUTES, true)) {
            file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
                "  -> Skipped (allowed route)\n", FILE_APPEND);
            return;
        }

        // Skip for API routes and assets
        if (str_starts_with($route, 'api_') || 
            str_starts_with($request->getPathInfo(), '/api/') ||
            str_starts_with($request->getPathInfo(), '/build/') ||
            str_starts_with($request->getPathInfo(), '/uploads/')) {
            file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
                "  -> Skipped (API/assets)\n", FILE_APPEND);
            return;
        }

        $token = $this->tokenStorage->getToken();
        
        // Skip if no token or user is not authenticated
        if (!$token || !$token->getUser()) {
            file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
                "  -> Skipped (no user)\n", FILE_APPEND);
            return;
        }

        $user = $token->getUser();
        
        // Skip if user is not our User entity
        if (!$user instanceof User) {
            file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
                "  -> Skipped (not User entity)\n", FILE_APPEND);
            return;
        }

        // Check if profile exists and is complete
        $profile = $user->getProfile();
        
        file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
            "  -> User: " . $user->getEmail() . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
            "  -> Profile exists: " . ($profile ? 'YES' : 'NO') . "\n", FILE_APPEND);
        file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
            "  -> Profile complete: " . ($profile && $profile->isComplete() ? 'YES' : 'NO') . "\n", FILE_APPEND);
        
        if (!$profile || !$profile->isComplete()) {
            file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
                "  -> REDIRECTING to /profile/complete\n", FILE_APPEND);
            // Redirect to profile completion page
            $url = $this->router->generate('app_profile_complete');
            $response = new RedirectResponse($url);
            $event->setResponse($response);
        } else {
            file_put_contents(__DIR__ . '/../../var/log/profile_check.log', 
                "  -> Profile is complete, allowing access\n", FILE_APPEND);
        }
    }
}