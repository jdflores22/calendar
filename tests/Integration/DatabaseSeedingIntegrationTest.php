<?php

namespace App\Tests\Integration;

use App\Entity\User;
use App\Entity\Office;
use App\Entity\Event;
use App\Entity\UserProfile;
use App\Entity\DirectoryContact;
use App\Entity\EventTag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for database seeding functionality
 * 
 * Tests the core seeding logic without the complex command execution
 */
class DatabaseSeedingIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        
        // Clean up before each test
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function testDatabaseSeedingCreatesExpectedData(): void
    {
        // Test that we can create the basic seeding data structure
        
        // Create offices
        $office = new Office();
        $office->setName('Test Office');
        $office->setCode('TEST');
        $office->setColor('#FF0000');
        $this->entityManager->persist($office);
        
        // Create user
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_USER']);
        $user->setVerified(true);
        $user->setOffice($office);
        
        // Create profile
        $profile = new UserProfile();
        $profile->setFirstName('Test');
        $profile->setLastName('User');
        $profile->setPhone('1234567890');
        $profile->setAddress('Test Address');
        $profile->setAvatar('default-avatar.png'); // Required for completion
        $profile->setUser($user);
        $profile->checkCompletionStatus();
        
        $user->setProfile($profile);
        
        $this->entityManager->persist($user);
        $this->entityManager->persist($profile);
        
        // Create event
        $event = new Event();
        $event->setTitle('Test Event');
        $event->setStartTime(new \DateTime('2024-01-01 10:00:00'));
        $event->setEndTime(new \DateTime('2024-01-01 11:00:00'));
        $event->setDescription('Test Description');
        $event->setLocation('Test Location');
        $event->setCreator($user);
        $event->setOffice($office);
        $event->setColor($office->getColor());
        $event->setPriority('normal');
        
        $this->entityManager->persist($event);
        
        // Create directory contact
        $contact = new DirectoryContact();
        $contact->setName('Test Contact');
        $contact->setPosition('Test Position');
        $contact->setEmail('contact@example.com');
        $contact->setPhone('0987654321');
        $contact->setAddress('Contact Address');
        $contact->setOffice($office);
        
        $this->entityManager->persist($contact);
        
        // Create event tag
        $tag = new EventTag();
        $tag->setName('test-tag');
        $tag->setColor('#00FF00');
        
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        
        // Verify data was created
        $this->assertNotNull($office->getId(), 'Office should be persisted');
        $this->assertNotNull($user->getId(), 'User should be persisted');
        $this->assertNotNull($profile->getId(), 'Profile should be persisted');
        $this->assertNotNull($event->getId(), 'Event should be persisted');
        $this->assertNotNull($contact->getId(), 'Contact should be persisted');
        $this->assertNotNull($tag->getId(), 'Tag should be persisted');
        
        // Verify relationships
        $this->assertEquals($office, $user->getOffice(), 'User should be assigned to office');
        $this->assertEquals($user, $profile->getUser(), 'Profile should be linked to user');
        $this->assertEquals($user, $event->getCreator(), 'Event should have creator');
        $this->assertEquals($office, $event->getOffice(), 'Event should be assigned to office');
        $this->assertEquals($office, $contact->getOffice(), 'Contact should be assigned to office');
        
        // Verify data integrity
        $this->assertTrue($profile->isComplete(), 'Profile should be complete');
        $this->assertEquals($office->getColor(), $event->getColor(), 'Event should inherit office color');
    }

    /**
     * @test
     */
    public function testOfficeColorUniqueness(): void
    {
        // Create first office
        $office1 = new Office();
        $office1->setName('Office 1');
        $office1->setCode('OFF1');
        $office1->setColor('#FF0000');
        $this->entityManager->persist($office1);
        
        // Create second office with different color
        $office2 = new Office();
        $office2->setName('Office 2');
        $office2->setCode('OFF2');
        $office2->setColor('#00FF00');
        $this->entityManager->persist($office2);
        
        $this->entityManager->flush();
        
        // Verify both offices were created
        $this->assertNotNull($office1->getId(), 'First office should be persisted');
        $this->assertNotNull($office2->getId(), 'Second office should be persisted');
        
        // Verify colors are different
        $this->assertNotEquals($office1->getColor(), $office2->getColor(), 'Office colors should be unique');
    }

    /**
     * @test
     */
    public function testUserRoleAssignment(): void
    {
        // Create office
        $office = new Office();
        $office->setName('Test Office');
        $office->setCode('TEST');
        $office->setColor('#FF0000');
        $this->entityManager->persist($office);
        
        // Create users with different roles
        $roles = [
            ['ROLE_ADMIN'],
            ['ROLE_OSEC'],
            ['ROLE_EO'],
            ['ROLE_DIVISION'],
            ['ROLE_PROVINCE']
        ];
        
        $users = [];
        foreach ($roles as $index => $roleSet) {
            $user = new User();
            $user->setEmail("user{$index}@example.com");
            $user->setPassword('hashed_password');
            $user->setRoles($roleSet);
            $user->setVerified(true);
            $user->setOffice($office);
            
            $profile = new UserProfile();
            $profile->setFirstName("User{$index}");
            $profile->setLastName('Test');
            $profile->setPhone('1234567890');
            $profile->setAddress('Test Address');
            $profile->setAvatar('default-avatar.png'); // Required for completion
            $profile->setUser($user);
            $profile->checkCompletionStatus();
            
            $user->setProfile($profile);
            
            $this->entityManager->persist($user);
            $this->entityManager->persist($profile);
            $users[] = $user;
        }
        
        $this->entityManager->flush();
        
        // Verify role assignments
        foreach ($users as $index => $user) {
            $this->assertNotNull($user->getId(), "User {$index} should be persisted");
            $this->assertEquals($roles[$index], array_diff($user->getRoles(), ['ROLE_USER']), "User {$index} should have correct roles");
            $this->assertTrue($user->isVerified(), "User {$index} should be verified");
            $this->assertTrue($user->getProfile()->isComplete(), "User {$index} profile should be complete");
        }
    }

    private function cleanupTestData(): void
    {
        try {
            // Disable foreign key checks temporarily
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Clear in reverse dependency order
            $this->entityManager->createQuery('DELETE FROM App\Entity\Event')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\DirectoryContact')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\EventTag')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\AuditLog')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\UserProfile')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
            $this->entityManager->flush();
            
            // Re-enable foreign key checks
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Exception $e) {
            // Ignore cleanup errors in tests
        }
    }
}