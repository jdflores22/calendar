<?php

namespace App\Tests\Property;

use App\Entity\Event;
use App\Entity\Office;
use App\Entity\User;
use App\Security\Voter\EventVoter;
use App\Security\Voter\UserVoter;
use App\Security\Voter\OfficeVoter;
use App\Security\Voter\DirectoryVoter;
use App\Security\Voter\FormBuilderVoter;
use App\Service\RoleHierarchyService;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @test
 * Feature: tesda-calendar-system, Property 3: Role-Based Permission Enforcement
 * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.7
 */
class RoleBasedPermissionPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private EventVoter $eventVoter;
    private UserVoter $userVoter;
    private OfficeVoter $officeVoter;
    private DirectoryVoter $directoryVoter;
    private FormBuilderVoter $formBuilderVoter;
    private RoleHierarchyService $roleHierarchyService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->eventVoter = new EventVoter();
        $this->userVoter = new UserVoter();
        $this->officeVoter = new OfficeVoter();
        $this->directoryVoter = new DirectoryVoter();
        $this->formBuilderVoter = new FormBuilderVoter();
        $this->roleHierarchyService = new RoleHierarchyService();
        
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
     * Property 3: Role-Based Permission Enforcement
     * For any authenticated user, their assigned role (Admin, OSEC, EO, Division, Province) 
     * must consistently determine their access permissions across all system features, with 
     * Admin having full access, OSEC having event override capabilities, and other roles 
     * having progressively restricted permissions
     * 
     * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5, 2.7
     */
    public function testRoleBasedEventPermissionConsistency(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE']),
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $userRole, string $eventCreatorRole) {
            // Create users with different roles
            $user = $this->createUser([$userRole]);
            $eventCreator = $this->createUser([$eventCreatorRole]);
            
            // Create office for testing
            $office = $this->createOffice();
            $user->setOffice($office);
            $eventCreator->setOffice($office);
            
            // Create event
            $event = $this->createEvent($eventCreator, $office);
            
            $token = $this->createToken($user);

            // Property: All users can view all events (universal visibility)
            $viewResult = $this->eventVoter->vote($token, $event, [EventVoter::VIEW]);
            $this->assertEquals(VoterInterface::ACCESS_GRANTED, $viewResult, 
                "All users must be able to view all events regardless of role");

            // Property: All users can create events
            $createResult = $this->eventVoter->vote($token, null, [EventVoter::CREATE]);
            $this->assertEquals(VoterInterface::ACCESS_GRANTED, $createResult, 
                "All users must be able to create events");

            // Property: Admin can edit/delete all events
            if ($user->hasRole('ROLE_ADMIN')) {
                $editResult = $this->eventVoter->vote($token, $event, [EventVoter::EDIT]);
                $deleteResult = $this->eventVoter->vote($token, $event, [EventVoter::DELETE]);
                
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $editResult, 
                    "Admin must be able to edit all events");
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $deleteResult, 
                    "Admin must be able to delete all events");
            }

            // Property: OSEC can edit/delete all events
            if ($user->hasRole('ROLE_OSEC')) {
                $editResult = $this->eventVoter->vote($token, $event, [EventVoter::EDIT]);
                $deleteResult = $this->eventVoter->vote($token, $event, [EventVoter::DELETE]);
                
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $editResult, 
                    "OSEC must be able to edit all events");
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $deleteResult, 
                    "OSEC must be able to delete all events");
            }

            // Property: Province can only edit/delete their own events
            if ($user->hasRole('ROLE_PROVINCE') && !$user->hasRole('ROLE_DIVISION') && 
                !$user->hasRole('ROLE_EO') && !$user->hasRole('ROLE_OSEC') && !$user->hasRole('ROLE_ADMIN')) {
                
                $editResult = $this->eventVoter->vote($token, $event, [EventVoter::EDIT]);
                $deleteResult = $this->eventVoter->vote($token, $event, [EventVoter::DELETE]);
                
                if ($event->getCreator() === $user) {
                    $this->assertEquals(VoterInterface::ACCESS_GRANTED, $editResult, 
                        "Province users must be able to edit their own events");
                    $this->assertEquals(VoterInterface::ACCESS_GRANTED, $deleteResult, 
                        "Province users must be able to delete their own events");
                } else {
                    $this->assertEquals(VoterInterface::ACCESS_DENIED, $editResult, 
                        "Province users must not be able to edit others' events");
                    $this->assertEquals(VoterInterface::ACCESS_DENIED, $deleteResult, 
                        "Province users must not be able to delete others' events");
                }
            }

            // Property: Only Admin and OSEC can override conflicts
            $overrideResult = $this->eventVoter->vote($token, null, [EventVoter::OVERRIDE_CONFLICT]);
            if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OSEC')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $overrideResult, 
                    "Admin and OSEC must be able to override scheduling conflicts");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $overrideResult, 
                    "Non-privileged users must not be able to override scheduling conflicts");
            }
        });
    }

    /**
     * Property: User Management Permission Hierarchy
     * User management permissions must follow role hierarchy
     * 
     * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
     */
    public function testUserManagementPermissionHierarchy(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE']),
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $userRole, string $targetUserRole) {
            $user = $this->createUser([$userRole]);
            $targetUser = $this->createUser([$targetUserRole]);
            
            $token = $this->createToken($user);

            // Property: Only Admin can create users
            $createResult = $this->userVoter->vote($token, null, [UserVoter::CREATE]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $createResult, 
                    "Admin must be able to create users");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $createResult, 
                    "Non-admin users must not be able to create users");
            }

            // Property: Only Admin can manage roles
            $manageRolesResult = $this->userVoter->vote($token, $targetUser, [UserVoter::MANAGE_ROLES]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $manageRolesResult, 
                    "Admin must be able to manage user roles");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $manageRolesResult, 
                    "Non-admin users must not be able to manage roles");
            }

            // Property: Users can edit their own profile
            $editProfileResult = $this->userVoter->vote($token, $user, [UserVoter::EDIT_PROFILE]);
            $this->assertEquals(VoterInterface::ACCESS_GRANTED, $editProfileResult, 
                "Users must be able to edit their own profile");

            // Property: Role hierarchy determines user visibility
            $viewResult = $this->userVoter->vote($token, $targetUser, [UserVoter::VIEW]);
            $userLevel = $this->roleHierarchyService->getUserRoleLevel($user);
            $targetLevel = $this->roleHierarchyService->getUserRoleLevel($targetUser);
            
            if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OSEC') || $user === $targetUser) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $viewResult, 
                    "Admin, OSEC, or same user must be able to view user details");
            }
        });
    }

    /**
     * Property: Office Management Permission Consistency
     * Office management permissions must be consistent with role hierarchy
     * 
     * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
     */
    public function testOfficeManagementPermissionConsistency(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $userRole) {
            $user = $this->createUser([$userRole]);
            $office = $this->createOffice();
            
            $token = $this->createToken($user);

            // Property: All users can view offices
            $viewResult = $this->officeVoter->vote($token, $office, [OfficeVoter::VIEW]);
            $this->assertEquals(VoterInterface::ACCESS_GRANTED, $viewResult, 
                "All users must be able to view offices");

            // Property: Only Admin can create offices
            $createResult = $this->officeVoter->vote($token, null, [OfficeVoter::CREATE]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $createResult, 
                    "Admin must be able to create offices");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $createResult, 
                    "Non-admin users must not be able to create offices");
            }

            // Property: Admin and OSEC can manage colors
            $manageColorsResult = $this->officeVoter->vote($token, $office, [OfficeVoter::MANAGE_COLORS]);
            if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_OSEC')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $manageColorsResult, 
                    "Admin and OSEC must be able to manage office colors");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $manageColorsResult, 
                    "Lower-level users must not be able to manage office colors");
            }

            // Property: Only Admin can assign users to offices
            $assignUsersResult = $this->officeVoter->vote($token, $office, [OfficeVoter::ASSIGN_USERS]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $assignUsersResult, 
                    "Admin must be able to assign users to offices");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $assignUsersResult, 
                    "Non-admin users must not be able to assign users to offices");
            }
        });
    }

    /**
     * Property: Admin-Only Feature Access
     * Certain features must be accessible only to Admin users
     * 
     * Validates: Requirements 2.1, 2.7
     */
    public function testAdminOnlyFeatureAccess(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $userRole) {
            $user = $this->createUser([$userRole]);
            $token = $this->createToken($user);

            // Property: Only Admin can access directory management
            $directoryAccessResult = $this->directoryVoter->vote($token, null, [DirectoryVoter::MANAGE]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $directoryAccessResult, 
                    "Admin must be able to access directory management");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $directoryAccessResult, 
                    "Non-admin users must not be able to access directory management");
            }

            // Property: Only Admin can access form builder
            $formBuilderAccessResult = $this->formBuilderVoter->vote($token, null, [FormBuilderVoter::ACCESS]);
            if ($user->hasRole('ROLE_ADMIN')) {
                $this->assertEquals(VoterInterface::ACCESS_GRANTED, $formBuilderAccessResult, 
                    "Admin must be able to access form builder");
            } else {
                $this->assertEquals(VoterInterface::ACCESS_DENIED, $formBuilderAccessResult, 
                    "Non-admin users must not be able to access form builder");
            }
        });
    }

    /**
     * Property: Role Hierarchy Service Consistency
     * Role hierarchy service must correctly determine authority levels
     * 
     * Validates: Requirements 2.1, 2.2, 2.3, 2.4, 2.5
     */
    public function testRoleHierarchyServiceConsistency(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE']),
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $role1, string $role2) {
            $user1 = $this->createUser([$role1]);
            $user2 = $this->createUser([$role2]);

            // Property: Role levels must be consistent with hierarchy
            $level1 = $this->roleHierarchyService->getUserRoleLevel($user1);
            $level2 = $this->roleHierarchyService->getUserRoleLevel($user2);

            // Admin should have highest level
            if ($user1->hasRole('ROLE_ADMIN')) {
                $this->assertGreaterThanOrEqual($level2, $level1, 
                    "Admin must have higher or equal authority level than other roles");
            }

            // OSEC should have higher level than EO, Division, Province
            if ($user1->hasRole('ROLE_OSEC') && !$user2->hasRole('ROLE_ADMIN')) {
                $this->assertGreaterThanOrEqual($level2, $level1, 
                    "OSEC must have higher or equal authority level than non-admin roles");
            }

            // Property: Authority comparison must be transitive
            if ($level1 > $level2) {
                $this->assertTrue($this->roleHierarchyService->hasHigherAuthority($user1, $user2), 
                    "Higher level user must have higher authority");
                $this->assertFalse($this->roleHierarchyService->hasHigherAuthority($user2, $user1), 
                    "Lower level user must not have higher authority");
            }

            // Property: Primary role must be the highest authority role
            $primaryRole1 = $this->roleHierarchyService->getPrimaryRole($user1);
            $this->assertEquals($role1, $primaryRole1, 
                "Primary role must match the assigned role for single-role users");
        });
    }

    private function createUser(array $roles): User
    {
        $user = new User();
        $user->setEmail('test' . random_int(1000, 9999) . '@example.com');
        $user->setPassword('password');
        $user->setRoles($roles);
        $user->setVerified(true);
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($user);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($user, random_int(1, 1000));
        
        return $user;
    }

    private function createOffice(): Office
    {
        $office = new Office();
        $office->setName('Test Office ' . random_int(1000, 9999));
        $office->setCode('TO' . random_int(100, 999));
        $office->setColor('#' . substr(md5(random_int(1, 1000)), 0, 6));
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($office);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($office, random_int(1, 1000));
        
        return $office;
    }

    private function createEvent(User $creator, Office $office): Event
    {
        $event = new Event();
        $event->setTitle('Test Event ' . random_int(1000, 9999));
        $event->setStartTime(new \DateTime());
        $event->setEndTime(new \DateTime('+1 hour'));
        $event->setCreator($creator);
        $event->setOffice($office);
        $event->setColor($office->getColor());
        
        // Use reflection to set ID for testing
        $reflection = new \ReflectionClass($event);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($event, random_int(1, 1000));
        
        return $event;
    }

    private function createToken(User $user): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);
        return $token;
    }
}