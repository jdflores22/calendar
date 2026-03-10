<?php

namespace App\Tests\Property;

use App\Entity\Office;
use App\Entity\User;
use App\Entity\UserProfile;
use App\EventListener\ProfileCompletionListener;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @test
 * Feature: tesda-calendar-system, Property 4: Profile Completion Gate
 * Validates: Requirements 3.1, 3.2, 3.3, 3.5
 */
class ProfileCompletionGatePropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private TokenStorageInterface $tokenStorage;
    private RouterInterface $router;
    private ProfileCompletionListener $listener;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        
        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->tokenStorage = $container->get(TokenStorageInterface::class);
        $this->router = $container->get(RouterInterface::class);
        
        $this->listener = new ProfileCompletionListener($this->tokenStorage, $this->router);
        
        // Clear any existing data in correct order (children first, then parents)
        $this->entityManager->createQuery('DELETE FROM App\Entity\EventAttachment')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Event')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserProfile')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AuditLog')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Property 4: Profile Completion Gate
     * For any user with an incomplete profile, access to main system features must be prevented 
     * until all required fields (name, office assignment, role, contact details, avatar) are 
     * completed and validated
     * 
     * Validates: Requirements 3.1, 3.2, 3.3, 3.5
     */
    public function testProfileCompletionGateEnforcement(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\string(),
            Generator\string(),
            Generator\elements(['/', '/dashboard', '/calendar', '/events', '/directory']),
            Generator\bool() // Whether to make profile complete
        )->then(function (string $email, string $firstName, string $lastName, string $requestPath, bool $makeComplete) {
            // Skip empty names
            if (empty(trim($firstName)) || empty(trim($lastName))) {
                return;
            }

            // Create office for testing
            $office = new Office();
            $office->setName('Test Office ' . substr(md5($email), 0, 8));
            $office->setCode('TO' . substr(md5($email), 0, 4));
            $office->setColor('#' . substr(md5($email), 0, 6));
            $this->entityManager->persist($office);

            // Create user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password');
            $user->setVerified(true); // User must be verified to trigger profile gate
            $user->setRoles(['ROLE_USER']);

            // Create profile
            $profile = new UserProfile();
            $profile->setFirstName(trim($firstName));
            $profile->setLastName(trim($lastName));
            
            if ($makeComplete) {
                // Make profile complete
                $profile->setPhone('+63912345' . substr(md5($email), 0, 4));
                $profile->setAvatar('uploads/avatars/test-' . substr(md5($email), 0, 8) . '.jpg');
                $user->setOffice($office);
            } else {
                // Leave profile incomplete (missing phone, avatar, or office)
                if (rand(0, 1)) {
                    $profile->setPhone('+63912345' . substr(md5($email), 0, 4));
                }
                if (rand(0, 1)) {
                    $profile->setAvatar('uploads/avatars/test-' . substr(md5($email), 0, 8) . '.jpg');
                }
                if (rand(0, 1)) {
                    $user->setOffice($office);
                }
            }

            $user->setProfile($profile);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Property: Profile completion status must be correctly determined
            $profile->checkCompletionStatus();
            $expectedComplete = !empty($profile->getFirstName()) && 
                               !empty($profile->getLastName()) && 
                               !empty($profile->getPhone()) && 
                               !empty($profile->getAvatar()) && 
                               $user->getOffice() !== null;
            
            $this->assertEquals($expectedComplete, $profile->isComplete(), 
                'Profile completion status must match expected state based on required fields');

            // Create authentication token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);

            // Create request event
            $request = Request::create($requestPath);
            $request->attributes->set('_route', $this->getRouteFromPath($requestPath));
            
            $kernel = static::getContainer()->get('kernel');
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

            // Apply the listener
            $this->listener->__invoke($event);

            if ($profile->isComplete()) {
                // Property: Complete profiles must not be redirected
                $this->assertNull($event->getResponse(), 
                    'Users with complete profiles must not be redirected');
            } else {
                // Property: Incomplete profiles must be redirected to profile completion
                $response = $event->getResponse();
                $this->assertNotNull($response, 
                    'Users with incomplete profiles must be redirected');
                
                if ($response) {
                    $this->assertEquals(302, $response->getStatusCode(), 
                        'Redirect response must have 302 status code');
                    
                    $this->assertStringContainsString('/profile/complete', $response->getTargetUrl(), 
                        'Incomplete profiles must be redirected to profile completion page');
                }
            }

            // Clean up
            $this->tokenStorage->setToken(null);
            $this->entityManager->remove($user);
            $this->entityManager->remove($office);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Allowed Routes Bypass
     * Profile completion gate must allow access to specific routes even with incomplete profiles
     * 
     * Validates: Requirements 3.5
     */
    public function testAllowedRoutesBypass(): void
    {
        $allowedRoutes = [
            'app_profile_complete',
            'app_profile_edit', 
            'app_profile_show',
            'app_logout'
        ];

        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\elements($allowedRoutes)
        )->then(function (string $email, string $allowedRoute) {
            // Create user with incomplete profile
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password');
            $user->setVerified(true);
            $user->setRoles(['ROLE_USER']);

            $profile = new UserProfile();
            $profile->setFirstName('Test');
            $profile->setLastName('User');
            // Intentionally leave profile incomplete (no phone, avatar, office)
            
            $user->setProfile($profile);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Property: Profile must be incomplete
            $this->assertFalse($profile->isComplete(), 
                'Profile must be incomplete for this test');

            // Create authentication token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);

            // Create request for allowed route
            $request = Request::create('/test-path');
            $request->attributes->set('_route', $allowedRoute);
            
            $kernel = static::getContainer()->get('kernel');
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

            // Apply the listener
            $this->listener->__invoke($event);

            // Property: Allowed routes must not trigger redirects even with incomplete profiles
            $this->assertNull($event->getResponse(), 
                "Route '{$allowedRoute}' must be accessible even with incomplete profile");

            // Clean up
            $this->tokenStorage->setToken(null);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Unverified Users Bypass
     * Profile completion gate must not affect unverified users
     * 
     * Validates: Requirements 3.5
     */
    public function testUnverifiedUsersBypass(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\elements(['/', '/dashboard', '/calendar'])
        )->then(function (string $email, string $requestPath) {
            // Create unverified user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password');
            $user->setVerified(false); // User is not verified
            $user->setRoles(['ROLE_USER']);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create authentication token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);

            // Create request event
            $request = Request::create($requestPath);
            $request->attributes->set('_route', $this->getRouteFromPath($requestPath));
            
            $kernel = static::getContainer()->get('kernel');
            $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

            // Apply the listener
            $this->listener->__invoke($event);

            // Property: Unverified users must not be affected by profile completion gate
            $this->assertNull($event->getResponse(), 
                'Unverified users must not be redirected by profile completion gate');

            // Clean up
            $this->tokenStorage->setToken(null);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Required Fields Validation
     * Profile completion must require all specified fields: name, office assignment, contact details, avatar
     * 
     * Validates: Requirements 3.1, 3.2, 3.3
     */
    public function testRequiredFieldsValidation(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\bool(), // Has first name
            Generator\bool(), // Has last name  
            Generator\bool(), // Has phone
            Generator\bool(), // Has avatar
            Generator\bool()  // Has office
        )->then(function (string $email, bool $hasFirstName, bool $hasLastName, bool $hasPhone, bool $hasAvatar, bool $hasOffice) {
            // Skip combinations where required database fields are missing
            // (firstName and lastName are required by database constraints)
            if (!$hasFirstName || !$hasLastName) {
                return;
            }

            // Create office for testing
            $office = new Office();
            $office->setName('Test Office ' . substr(md5($email), 0, 8));
            $office->setCode('TO' . substr(md5($email), 0, 4));
            $office->setColor('#' . substr(md5($email), 0, 6));
            $this->entityManager->persist($office);

            // Create user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password');
            $user->setVerified(true);
            $user->setRoles(['ROLE_USER']);

            // Create profile with conditional fields
            $profile = new UserProfile();
            
            // Always set required database fields
            $profile->setFirstName('Test');
            $profile->setLastName('User');
            
            if ($hasPhone) {
                $profile->setPhone('+63912345678');
            }
            
            if ($hasAvatar) {
                $profile->setAvatar('uploads/avatars/test.jpg');
            }
            
            if ($hasOffice) {
                $user->setOffice($office);
            }

            $user->setProfile($profile);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Check completion status
            $profile->checkCompletionStatus();
            
            // Property: Profile is complete only if ALL required fields are present
            // (firstName and lastName are always present in this test, so we check phone, avatar, office)
            $expectedComplete = $hasPhone && $hasAvatar && $hasOffice;
            $this->assertEquals($expectedComplete, $profile->isComplete(), 
                'Profile completion must require ALL required fields: firstName, lastName, phone, avatar, and office assignment');

            // Clean up
            $this->entityManager->remove($user);
            $this->entityManager->remove($office);
            $this->entityManager->flush();
        });
    }

    private function getRouteFromPath(string $path): string
    {
        return match ($path) {
            '/' => 'app_dashboard',
            '/dashboard' => 'app_dashboard', 
            '/calendar' => 'app_calendar',
            '/events' => 'app_events',
            '/directory' => 'app_directory',
            default => 'app_dashboard'
        };
    }
}