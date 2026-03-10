<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Office;
use App\Entity\User;
use App\Entity\UserProfile;
use App\EventListener\ProfileCompletionListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ProfileCompletionListenerTest extends TestCase
{
    private TokenStorageInterface $tokenStorage;
    private RouterInterface $router;
    private ProfileCompletionListener $listener;

    protected function setUp(): void
    {
        $this->tokenStorage = $this->createMock(TokenStorageInterface::class);
        $this->router = $this->createMock(RouterInterface::class);
        $this->listener = new ProfileCompletionListener($this->tokenStorage, $this->router);
    }

    public function testRedirectsIncompleteProfileToCompletionPage(): void
    {
        // Create user with incomplete profile
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setVerified(true);
        
        $profile = new UserProfile();
        $profile->setFirstName('Test');
        $profile->setLastName('User');
        // Missing phone, avatar, and office - profile is incomplete
        
        $user->setProfile($profile);

        // Mock token
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        // Mock router
        $this->router->expects($this->once())
            ->method('generate')
            ->with('app_profile_complete')
            ->willReturn('/profile/complete');

        // Create request event
        $request = Request::create('/dashboard');
        $request->attributes->set('_route', 'app_dashboard');
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Execute listener
        $this->listener->__invoke($event);

        // Assert redirect response
        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('/profile/complete', $response->getTargetUrl());
    }

    public function testAllowsCompleteProfileAccess(): void
    {
        // Create user with complete profile
        $office = new Office();
        $office->setName('Test Office');
        $office->setCode('TO');
        $office->setColor('#FF0000');

        $user = new User();
        $user->setEmail('test@example.com');
        $user->setVerified(true);
        $user->setOffice($office);
        
        $profile = new UserProfile();
        $profile->setFirstName('Test');
        $profile->setLastName('User');
        $profile->setPhone('+639123456789');
        $profile->setAvatar('uploads/avatars/test.jpg');
        
        $user->setProfile($profile);
        $profile->checkCompletionStatus(); // This should mark profile as complete

        // Mock token
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        // Router should not be called
        $this->router->expects($this->never())
            ->method('generate');

        // Create request event
        $request = Request::create('/dashboard');
        $request->attributes->set('_route', 'app_dashboard');
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Execute listener
        $this->listener->__invoke($event);

        // Assert no redirect
        $this->assertNull($event->getResponse());
    }

    public function testAllowsAccessToAllowedRoutes(): void
    {
        // For allowed routes, the listener should return early without checking tokens
        // So we don't expect any method calls on tokenStorage or router
        
        $this->tokenStorage->expects($this->never())
            ->method('getToken');

        $this->router->expects($this->never())
            ->method('generate');

        // Create request event for allowed route
        $request = Request::create('/profile/complete');
        $request->attributes->set('_route', 'app_profile_complete');
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Execute listener
        $this->listener->__invoke($event);

        // Assert no redirect for allowed route
        $this->assertNull($event->getResponse());
    }

    public function testIgnoresUnverifiedUsers(): void
    {
        // Create unverified user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setVerified(false); // User is not verified

        // Mock token
        $token = new UsernamePasswordToken($user, 'main', ['ROLE_USER']);
        $this->tokenStorage->expects($this->once())
            ->method('getToken')
            ->willReturn($token);

        // Router should not be called
        $this->router->expects($this->never())
            ->method('generate');

        // Create request event
        $request = Request::create('/dashboard');
        $request->attributes->set('_route', 'app_dashboard');
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Execute listener
        $this->listener->__invoke($event);

        // Assert no redirect for unverified user
        $this->assertNull($event->getResponse());
    }
}