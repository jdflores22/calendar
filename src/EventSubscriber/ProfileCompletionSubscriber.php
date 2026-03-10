<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Entity\User;

class ProfileCompletionSubscriber implements EventSubscriberInterface
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
        private UrlGeneratorInterface $urlGenerator
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        // Skip check for allowed routes
        if (in_array($route, self::ALLOWED_ROUTES)) {
            return;
        }

        // Skip check for API routes and assets
        if (str_starts_with($route, 'api_') || 
            str_starts_with($request->getPathInfo(), '/bundles/') ||
            str_starts_with($request->getPathInfo(), '/uploads/')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        // Check if profile exists and is complete
        $profile = $user->getProfile();
        if (!$profile || !$profile->isComplete()) {
            $profileCompleteUrl = $this->urlGenerator->generate('app_profile_complete');
            
            // Don't redirect if already on profile complete page
            if ($request->getPathInfo() !== $profileCompleteUrl) {
                $event->setResponse(new RedirectResponse($profileCompleteUrl));
            }
        }
    }
}
