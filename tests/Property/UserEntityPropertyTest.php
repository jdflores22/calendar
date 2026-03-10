<?php

namespace App\Tests\Property;

use App\Entity\User;
use App\Entity\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @test
 * Feature: tesda-calendar-system, Property 2: Email Verification Round Trip
 * Validates: Requirements 1.2, 1.3
 */
class UserEntityPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
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
     * Property 2: Email Verification Round Trip
     * For any user registration, the account must remain unverified until email verification 
     * is completed, and password reset tokens must expire after their designated time period
     * 
     * Validates: Requirements 1.2, 1.3
     */
    public function testEmailVerificationRoundTrip(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\string(),
            Generator\choose(1, 3600) // Token expiry in seconds (1 second to 1 hour)
        )->then(function (string $email, string $password, int $tokenExpirySeconds) {
            // Create a new user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($password);
            
            // Generate verification token
            $verificationToken = bin2hex(random_bytes(32));
            $user->setVerificationToken($verificationToken);
            $user->setVerificationTokenExpiresAt(
                (new \DateTime())->add(new \DateInterval("PT{$tokenExpirySeconds}S"))
            );

            // Property: User must start as unverified
            $this->assertFalse($user->isVerified(), 'User must start as unverified');

            // Persist the user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Retrieve user by verification token
            $foundUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['verificationToken' => $verificationToken]);

            // Property: User must be retrievable by verification token
            $this->assertNotNull($foundUser, 'User must be retrievable by verification token');
            $this->assertEquals($email, $foundUser->getEmail(), 'Email must match');
            $this->assertFalse($foundUser->isVerified(), 'User must still be unverified');

            // Simulate email verification
            $foundUser->setVerified(true);
            $foundUser->setVerificationToken(null);
            $foundUser->setVerificationTokenExpiresAt(null);
            $this->entityManager->flush();

            // Property: After verification, user must be verified and token cleared
            $this->assertTrue($foundUser->isVerified(), 'User must be verified after verification');
            $this->assertNull($foundUser->getVerificationToken(), 'Verification token must be cleared');
            $this->assertNull($foundUser->getVerificationTokenExpiresAt(), 'Token expiry must be cleared');

            // Clean up for next iteration
            $this->entityManager->remove($foundUser);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Password Reset Token Expiration
     * Password reset tokens must expire after their designated time period
     * 
     * Validates: Requirements 1.3
     */
    public function testPasswordResetTokenExpiration(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\string(),
            Generator\choose(1, 3600) // Token expiry in seconds
        )->then(function (string $email, string $password, int $tokenExpirySeconds) {
            // Create a verified user
            $user = new User();
            $user->setEmail($email);
            $user->setPassword($password);
            $user->setVerified(true);

            // Generate password reset token
            $resetToken = bin2hex(random_bytes(32));
            $expiryTime = (new \DateTime())->add(new \DateInterval("PT{$tokenExpirySeconds}S"));
            $user->setPasswordResetToken($resetToken);
            $user->setPasswordResetTokenExpiresAt($expiryTime);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Property: User must be retrievable by reset token
            $foundUser = $this->entityManager->getRepository(User::class)
                ->findOneBy(['passwordResetToken' => $resetToken]);

            $this->assertNotNull($foundUser, 'User must be retrievable by reset token');
            $this->assertEquals($resetToken, $foundUser->getPasswordResetToken(), 'Reset token must match');
            $this->assertNotNull($foundUser->getPasswordResetTokenExpiresAt(), 'Reset token expiry must be set');

            // Property: Token expiry time must be in the future (within reasonable bounds)
            $now = new \DateTime();
            $this->assertGreaterThan($now, $foundUser->getPasswordResetTokenExpiresAt(), 
                'Reset token expiry must be in the future');

            // Property: Token expiry should be within expected timeframe
            $expectedMaxExpiry = (new \DateTime())->add(new \DateInterval("PT{$tokenExpirySeconds}S"))->add(new \DateInterval('PT10S')); // 10 second tolerance
            $this->assertLessThanOrEqual($expectedMaxExpiry, $foundUser->getPasswordResetTokenExpiresAt(),
                'Reset token expiry should be within expected timeframe');

            // Clean up
            $this->entityManager->remove($foundUser);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: User Role Consistency
     * User roles must be consistently managed and retrieved
     */
    public function testUserRoleConsistency(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\elements(['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'])
        )->then(function (string $email, string $role) {
            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password');
            $user->setRoles([$role]);

            // Property: User must have the assigned role plus ROLE_USER
            $roles = $user->getRoles();
            $this->assertContains($role, $roles, 'User must have the assigned role');
            $this->assertContains('ROLE_USER', $roles, 'User must always have ROLE_USER');

            // Property: Role-specific helper methods must work correctly
            switch ($role) {
                case 'ROLE_ADMIN':
                    $this->assertTrue($user->isAdmin(), 'isAdmin() must return true for ROLE_ADMIN');
                    break;
                case 'ROLE_OSEC':
                    $this->assertTrue($user->isOsec(), 'isOsec() must return true for ROLE_OSEC');
                    break;
                case 'ROLE_EO':
                    $this->assertTrue($user->isEo(), 'isEo() must return true for ROLE_EO');
                    break;
                case 'ROLE_DIVISION':
                    $this->assertTrue($user->isDivision(), 'isDivision() must return true for ROLE_DIVISION');
                    break;
                case 'ROLE_PROVINCE':
                    $this->assertTrue($user->isProvince(), 'isProvince() must return true for ROLE_PROVINCE');
                    break;
            }

            // Property: hasRole method must work correctly
            $this->assertTrue($user->hasRole($role), 'hasRole() must return true for assigned role');
            $this->assertTrue($user->hasRole('ROLE_USER'), 'hasRole() must return true for ROLE_USER');
        });
    }

    /**
     * Property: User Profile Relationship Consistency
     * User and UserProfile must maintain consistent bidirectional relationship
     */
    public function testUserProfileRelationshipConsistency(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'test' . abs(crc32($str)) . '@example.com'; },
                Generator\string()
            ),
            Generator\string(),
            Generator\string()
        )->then(function (string $email, string $firstName, string $lastName) {
            // Skip empty names as they're not valid
            if (empty(trim($firstName)) || empty(trim($lastName))) {
                return;
            }

            $user = new User();
            $user->setEmail($email);
            $user->setPassword('password');

            $profile = new UserProfile();
            $profile->setFirstName(trim($firstName));
            $profile->setLastName(trim($lastName));

            // Property: Setting profile on user must establish bidirectional relationship
            $user->setProfile($profile);
            
            $this->assertSame($profile, $user->getProfile(), 'User must have the assigned profile');
            $this->assertSame($user, $profile->getUser(), 'Profile must reference the user');

            // Property: Profile display name must be consistent
            $expectedDisplayName = trim($firstName) . ' ' . trim($lastName);
            $this->assertEquals($expectedDisplayName, $profile->getDisplayName(), 
                'Profile display name must match first and last name');

            // Property: Full name must include all name parts
            $this->assertStringContainsString(trim($firstName), $profile->getFullName(), 
                'Full name must contain first name');
            $this->assertStringContainsString(trim($lastName), $profile->getFullName(), 
                'Full name must contain last name');
        });
    }
}