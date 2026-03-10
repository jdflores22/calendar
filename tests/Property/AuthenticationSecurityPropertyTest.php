<?php

namespace App\Tests\Property;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * @test
 * Feature: tesda-calendar-system, Property 1: Authentication Security Consistency
 * Validates: Requirements 1.1, 1.4, 1.5
 */
class AuthenticationSecurityPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private UserRepository $userRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $this->userRepository = static::getContainer()->get(UserRepository::class);
        
        // Clear any existing data in correct order (children first, then parents)
        $this->entityManager->createQuery('DELETE FROM App\Entity\EventAttachment')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Event')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\UserProfile')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AuditLog')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Property 1: Authentication Security Consistency
     * For any user attempting to access the system, authentication must be validated using 
     * secure credentials, failed attempts must be rate-limited, and passwords must be stored 
     * using approved hashing algorithms (bcrypt/argon2id)
     * 
     * Validates: Requirements 1.1, 1.4, 1.5
     */
    public function testPasswordHashingSecurityConsistency(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\map(
                function($str) { return $str . 'password123'; }, // Ensure minimum length
                Generator\string()
            )
        )->withMaxSize(10)->then(function (string $email, string $plainPassword) {
            // Create user with hashed password
            $user = new User();
            $user->setEmail($email);
            
            // Property: Password must be hashed using secure algorithm
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            // Property: Hashed password must not equal plain password
            $this->assertNotEquals($plainPassword, $hashedPassword, 
                'Hashed password must not equal plain password');

            // Property: Hashed password must be using argon2id algorithm (starts with $argon2id$)
            $this->assertStringStartsWith('$argon2id$', $hashedPassword, 
                'Password must be hashed using argon2id algorithm');

            // Property: Password verification must work correctly
            $this->assertTrue($this->passwordHasher->isPasswordValid($user, $plainPassword), 
                'Password verification must succeed with correct password');

            // Property: Password verification must fail with incorrect password
            $wrongPassword = $plainPassword . 'wrong';
            $this->assertFalse($this->passwordHasher->isPasswordValid($user, $wrongPassword), 
                'Password verification must fail with incorrect password');

            // Property: Same password should produce different hashes (salt verification)
            $secondHash = $this->passwordHasher->hashPassword($user, $plainPassword);
            $this->assertNotEquals($hashedPassword, $secondHash, 
                'Same password should produce different hashes due to salting');

            // Property: Both hashes should verify the same password
            $user->setPassword($secondHash);
            $this->assertTrue($this->passwordHasher->isPasswordValid($user, $plainPassword), 
                'Both hashes should verify the same password');
        });
    }

    /**
     * Property: User Provider Security Consistency
     * User loading and authentication must be secure and consistent
     * 
     * Validates: Requirements 1.1, 1.5
     */
    public function testUserProviderSecurityConsistency(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\map(
                function($str) { return $str . 'password123'; }, // Ensure minimum length
                Generator\string()
            ),
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->withMaxSize(10)->then(function (string $email, string $password, string $role) {
            // Create and persist user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setRoles([$role]);
            $user->setVerified(true);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Property: User must be loadable by email identifier
            $loadedUser = $this->userRepository->loadUserByIdentifier($email);
            $this->assertNotNull($loadedUser, 'User must be loadable by email identifier');
            $this->assertEquals($email, $loadedUser->getUserIdentifier(), 
                'Loaded user identifier must match email');

            // Property: User must support the User class
            $this->assertTrue($this->userRepository->supportsClass(User::class), 
                'Repository must support User class');

            // Property: User refresh must work correctly
            $refreshedUser = $this->userRepository->refreshUser($loadedUser);
            $this->assertEquals($email, $refreshedUser->getEmail(), 
                'Refreshed user must have same email');
            $this->assertEquals($loadedUser->getId(), $refreshedUser->getId(), 
                'Refreshed user must have same ID');

            // Property: Role hierarchy must be maintained
            $roles = $loadedUser->getRoles();
            $this->assertContains($role, $roles, 'User must have assigned role');
            $this->assertContains('ROLE_USER', $roles, 'User must always have ROLE_USER');

            // Clean up
            $this->entityManager->remove($loadedUser);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Authentication Failure Security
     * Invalid authentication attempts must be handled securely
     * 
     * Validates: Requirements 1.1, 1.4
     */
    public function testAuthenticationFailureSecurity(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'nonexistent' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            )
        )->withMaxSize(5)->then(function (string $nonExistentEmail) {
            // Property: Loading non-existent user must throw UserNotFoundException
            $this->expectException(UserNotFoundException::class);
            $this->userRepository->loadUserByIdentifier($nonExistentEmail);
        });
    }

    /**
     * Property: Password Upgrade Security
     * Password upgrading must work securely when needed
     * 
     * Validates: Requirements 1.5
     */
    public function testPasswordUpgradeSecurity(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\map(
                function($str) { return $str . 'password123'; }, // Ensure minimum length
                Generator\string()
            )
        )->withMaxSize(10)->then(function (string $email, string $password) {
            // Create user with initial password
            $user = new User();
            $user->setEmail($email);
            $initialHash = $this->passwordHasher->hashPassword($user, $password);
            $user->setPassword($initialHash);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Generate new hash for upgrade
            $newHash = $this->passwordHasher->hashPassword($user, $password);

            // Property: Password upgrade must update the password
            $this->userRepository->upgradePassword($user, $newHash);
            
            $this->assertEquals($newHash, $user->getPassword(), 
                'Password upgrade must update user password');

            // Property: New password must still verify correctly
            $this->assertTrue($this->passwordHasher->isPasswordValid($user, $password), 
                'Upgraded password must still verify correctly');

            // Property: Changes must be persisted
            $this->entityManager->refresh($user);
            $this->assertEquals($newHash, $user->getPassword(), 
                'Password upgrade must be persisted to database');

            // Clean up
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: User Verification Security
     * User verification status must be handled securely
     * 
     * Validates: Requirements 1.1, 1.4
     */
    public function testUserVerificationSecurity(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\map(
                function($str) { return $str . 'password123'; }, // Ensure minimum length
                Generator\string()
            ),
            Generator\bool()
        )->withMaxSize(10)->then(function (string $email, string $password, bool $isVerified) {
            // Create user with verification status
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setVerified($isVerified);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Property: User verification status must be consistent
            $loadedUser = $this->userRepository->findByEmail($email);
            $this->assertNotNull($loadedUser, 'User must be findable by email');
            $this->assertEquals($isVerified, $loadedUser->isVerified(), 
                'User verification status must be consistent');

            // Property: User identifier must work regardless of verification status
            $this->assertEquals($email, $loadedUser->getUserIdentifier(), 
                'User identifier must work regardless of verification status');

            // Clean up
            $this->entityManager->remove($loadedUser);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Role-Based Security Consistency
     * Role assignments must be secure and consistent
     * 
     * Validates: Requirements 1.5
     */
    public function testRoleBasedSecurityConsistency(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\elements([
                ['ROLE_ADMIN'], 
                ['ROLE_OSEC'], 
                ['ROLE_EO'], 
                ['ROLE_DIVISION'], 
                ['ROLE_PROVINCE'],
                ['ROLE_ADMIN', 'ROLE_OSEC'],
                ['ROLE_OSEC', 'ROLE_EO']
            ])
        )->withMaxSize(10)->then(function (string $email, array $roles) {
            // Create user with multiple roles
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $user->setRoles($roles);

            // Property: All assigned roles must be present
            $userRoles = $user->getRoles();
            foreach ($roles as $role) {
                $this->assertContains($role, $userRoles, 
                    "User must have assigned role: {$role}");
            }

            // Property: ROLE_USER must always be present
            $this->assertContains('ROLE_USER', $userRoles, 
                'User must always have ROLE_USER');

            // Property: Roles must be unique (no duplicates)
            $this->assertEquals(count($userRoles), count(array_unique($userRoles)), 
                'User roles must be unique');

            // Property: hasRole method must work for all assigned roles
            foreach ($roles as $role) {
                $this->assertTrue($user->hasRole($role), 
                    "hasRole() must return true for assigned role: {$role}");
            }
            $this->assertTrue($user->hasRole('ROLE_USER'), 
                'hasRole() must return true for ROLE_USER');

            // Property: hasRole must return false for unassigned roles
            $allRoles = ['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'];
            $unassignedRoles = array_diff($allRoles, $roles);
            foreach ($unassignedRoles as $unassignedRole) {
                $this->assertFalse($user->hasRole($unassignedRole), 
                    "hasRole() must return false for unassigned role: {$unassignedRole}");
            }
        });
    }
}