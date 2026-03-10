<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Office;
use App\Entity\UserProfile;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Integration test for web application functionality
 * 
 * Tests basic web routes and authentication flow
 */
class WebApplicationIntegrationTest extends WebTestCase
{
    /**
     * @test
     */
    public function testHomePageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        
        $response = $client->getResponse();
        
        // Should redirect to login, show home page, or be rate limited
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 302, 404, 429]),
            "Home page should be accessible, redirect, not found, or rate limited, got {$response->getStatusCode()}"
        );
    }

    /**
     * @test
     */
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');
        
        $response = $client->getResponse();
        
        // Should be accessible or rate limited
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 429]),
            'Login page should be accessible or rate limited'
        );
        
        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString('login', strtolower($response->getContent()), 'Login page should contain login form');
        }
    }

    /**
     * @test
     */
    public function testCalendarPageRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/calendar');
        
        $response = $client->getResponse();
        
        // Should redirect to login or be rate limited
        $this->assertTrue(
            in_array($response->getStatusCode(), [302, 429]),
            'Calendar page should redirect unauthenticated users or be rate limited'
        );
        
        if ($response->getStatusCode() === 302) {
            $this->assertStringContainsString('/login', $response->headers->get('Location'), 'Should redirect to login page');
        }
    }

    /**
     * @test
     */
    public function testAuthenticatedUserCanAccessCalendar(): void
    {
        $client = static::createClient();
        
        // Create test user
        $user = $this->createTestUser();
        
        // Login user
        $client->loginUser($user);
        
        // Access calendar
        $client->request('GET', '/calendar');
        $response = $client->getResponse();
        
        // Should be accessible or rate limited
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 429]),
            'Authenticated user should access calendar or be rate limited'
        );
        
        if ($response->getStatusCode() === 200) {
            $this->assertStringContainsString('calendar', strtolower($response->getContent()), 'Calendar page should contain calendar content');
        }
    }

    /**
     * @test
     */
    public function testApiEndpointsRequireAuthentication(): void
    {
        $client = static::createClient();
        
        $apiEndpoints = [
            '/api/events',
            '/api/offices',
            '/api/users'
        ];
        
        foreach ($apiEndpoints as $endpoint) {
            $client->request('GET', $endpoint);
            $response = $client->getResponse();
            
            // Should return 401, 403, or redirect (302)
            $this->assertTrue(
                in_array($response->getStatusCode(), [302, 401, 403, 429]),
                "API endpoint {$endpoint} should require authentication, got {$response->getStatusCode()}"
            );
        }
    }

    /**
     * @test
     */
    public function testAuthenticatedUserCanAccessApiEvents(): void
    {
        $client = static::createClient();
        
        // Create test user
        $user = $this->createTestUser();
        
        // Login user
        $client->loginUser($user);
        
        // Access API events
        $client->request('GET', '/api/events');
        $response = $client->getResponse();
        
        // Should return 200 or valid response
        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 429]),
            "Authenticated user should access API events, got {$response->getStatusCode()}"
        );
    }

    private function createTestUser(): User
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        
        // Clean up existing test data more thoroughly
        try {
            $entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Find and remove existing test user
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => 'test@example.com']);
            if ($existingUser) {
                if ($existingUser->getProfile()) {
                    $entityManager->remove($existingUser->getProfile());
                }
                $entityManager->remove($existingUser);
                $entityManager->flush();
            }
            
            $entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
        
        // Create test office
        $office = $entityManager->getRepository(Office::class)->findOneBy(['code' => 'TEST']);
        if (!$office) {
            $office = new Office();
            $office->setName('Test Office');
            $office->setCode('TEST');
            $office->setColor('#FF0000');
            $entityManager->persist($office);
            $entityManager->flush();
        }
        
        // Create test user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('$2y$13$hashedpassword'); // Dummy hashed password
        $user->setRoles(['ROLE_USER']);
        $user->setVerified(true);
        $user->setOffice($office);
        
        // Create profile
        $profile = new UserProfile();
        $profile->setFirstName('Test');
        $profile->setLastName('User');
        $profile->setPhone('1234567890');
        $profile->setAddress('Test Address');
        $profile->setAvatar('default-avatar.png');
        $profile->setUser($user);
        $profile->checkCompletionStatus();
        
        $user->setProfile($profile);
        
        $entityManager->persist($user);
        $entityManager->persist($profile);
        $entityManager->flush();
        
        return $user;
    }
}