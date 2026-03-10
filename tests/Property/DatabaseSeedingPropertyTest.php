<?php

namespace App\Tests\Property;

use App\Command\SeedInitialDataCommand;
use App\Entity\User;
use App\Entity\Office;
use App\Entity\Event;
use App\Entity\UserProfile;
use App\Entity\DirectoryContact;
use App\Entity\EventTag;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Property 15: Database Seeding Consistency
 * 
 * For any database seeding operation, it must create all expected initial data
 * including default offices, roles, and system configurations in a consistent
 * and repeatable manner.
 * 
 * **Validates: Requirements 11.6**
 */
class DatabaseSeedingPropertyTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->passwordHasher = $container->get(UserPasswordHasherInterface::class);
        
        // Set up command tester
        $command = new SeedInitialDataCommand($this->entityManager, $this->passwordHasher);
        $this->commandTester = new CommandTester($command);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->clearTestData();
        parent::tearDown();
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that seeding creates all expected offices with unique colors and proper hierarchy
     */
    public function testSeedingCreatesExpectedOfficesWithUniqueColors(): void
    {
        // Clear existing data and run seeding
        $this->commandTester->execute(['--clear' => true]);
        
        // Property: All expected offices must be created
        $offices = $this->entityManager->getRepository(Office::class)->findAll();
        $this->assertGreaterThanOrEqual(10, count($offices), 
            'Seeding should create at least 10 offices');
        
        // Property: Each office must have a unique color
        $colors = [];
        $codes = [];
        foreach ($offices as $office) {
            $this->assertNotNull($office->getColor(), 
                "Office {$office->getName()} must have a color");
            $this->assertNotNull($office->getCode(), 
                "Office {$office->getName()} must have a code");
            
            // Check color uniqueness
            $this->assertNotContains($office->getColor(), $colors, 
                "Office color {$office->getColor()} must be unique");
            $colors[] = $office->getColor();
            
            // Check code uniqueness
            $this->assertNotContains($office->getCode(), $codes, 
                "Office code {$office->getCode()} must be unique");
            $codes[] = $office->getCode();
            
            // Property: Color must be valid hex format
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $office->getColor(), 
                "Office color must be valid hex format: {$office->getColor()}");
            
            // Property: Code must be valid format
            $this->assertMatchesRegularExpression('/^[A-Z0-9_-]+$/', $office->getCode(), 
                "Office code must be valid format: {$office->getCode()}");
        }
        
        // Property: Specific expected offices must exist
        $expectedOffices = ['OSEC', 'EO', 'PPDD', 'TESDD', 'RO4A', 'NCR'];
        foreach ($expectedOffices as $expectedCode) {
            $office = $this->entityManager->getRepository(Office::class)
                ->findOneBy(['code' => $expectedCode]);
            $this->assertNotNull($office, "Expected office {$expectedCode} must exist");
        }
        
        // Property: Office hierarchy must be properly established
        $parentOffices = $this->entityManager->getRepository(Office::class)
            ->findBy(['parent' => null]);
        $this->assertGreaterThan(0, count($parentOffices), 
            'There must be root offices without parents');
        
        $childOffices = $this->entityManager->createQuery(
            'SELECT o FROM App\Entity\Office o WHERE o.parent IS NOT NULL'
        )->getResult();
        $this->assertGreaterThan(0, count($childOffices), 
            'There must be child offices with parents');
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that seeding creates users with proper roles and complete profiles
     */
    public function testSeedingCreatesUsersWithProperRolesAndProfiles(): void
    {
        // Clear existing data and run seeding
        $this->commandTester->execute(['--clear' => true]);
        
        // Property: All expected users must be created
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $this->assertGreaterThanOrEqual(10, count($users), 
            'Seeding should create at least 10 users');
        
        // Property: Each user must have proper role assignment
        $expectedRoles = ['ROLE_ADMIN', 'ROLE_OSEC', 'ROLE_EO', 'ROLE_DIVISION', 'ROLE_PROVINCE'];
        $foundRoles = [];
        
        foreach ($users as $user) {
            // Property: User must have valid email
            $this->assertNotNull($user->getEmail(), 'User must have email');
            $this->assertStringContainsString('@', $user->getEmail(), 
                'User email must be valid format');
            
            // Property: User must have hashed password
            $this->assertNotNull($user->getPassword(), 'User must have password');
            $this->assertNotEquals('admin123', $user->getPassword(), 
                'Password must be hashed, not plain text');
            
            // Property: User must be verified
            $this->assertTrue($user->isVerified(), 
                'Seeded users must be verified');
            
            // Property: User must have at least one role beyond ROLE_USER
            $userRoles = $user->getRoles();
            $this->assertContains('ROLE_USER', $userRoles, 
                'User must have ROLE_USER');
            $this->assertGreaterThan(1, count($userRoles), 
                'User must have additional roles beyond ROLE_USER');
            
            // Collect found roles
            foreach ($userRoles as $role) {
                if ($role !== 'ROLE_USER') {
                    $foundRoles[] = $role;
                }
            }
            
            // Property: User must have complete profile
            $profile = $user->getProfile();
            $this->assertNotNull($profile, 'User must have profile');
            $this->assertNotNull($profile->getFirstName(), 
                'Profile must have first name');
            $this->assertNotNull($profile->getLastName(), 
                'Profile must have last name');
            $this->assertNotNull($profile->getPhone(), 
                'Profile must have phone');
            $this->assertTrue($profile->isComplete(), 
                'Profile must be marked as complete');
            
            // Property: User must be assigned to an office
            $this->assertNotNull($user->getOffice(), 
                'User must be assigned to an office');
        }
        
        // Property: All expected roles must be represented
        foreach ($expectedRoles as $expectedRole) {
            $this->assertContains($expectedRole, $foundRoles, 
                "Expected role {$expectedRole} must be assigned to at least one user");
        }
        
        // Property: Admin user must exist with proper credentials
        $adminUser = $this->entityManager->getRepository(User::class)
            ->findOneBy(['email' => 'admin@tesda.gov.ph']);
        $this->assertNotNull($adminUser, 'Admin user must exist');
        $this->assertTrue($adminUser->hasRole('ROLE_ADMIN'), 
            'Admin user must have ROLE_ADMIN');
        
        // Property: Password verification must work for seeded users
        $this->assertTrue($this->passwordHasher->isPasswordValid($adminUser, 'admin123'), 
            'Admin password must be verifiable');
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that seeding creates sample events with proper relationships
     */
    public function testSeedingCreatesSampleEventsWithProperRelationships(): void
    {
        // Clear existing data and run seeding
        $this->commandTester->execute(['--clear' => true]);
        
        // Property: Sample events must be created
        $events = $this->entityManager->getRepository(Event::class)->findAll();
        $this->assertGreaterThan(0, count($events), 
            'Seeding should create sample events');
        
        foreach ($events as $event) {
            // Property: Event must have required fields
            $this->assertNotNull($event->getTitle(), 'Event must have title');
            $this->assertNotNull($event->getStartTime(), 'Event must have start time');
            $this->assertNotNull($event->getEndTime(), 'Event must have end time');
            $this->assertNotNull($event->getColor(), 'Event must have color');
            
            // Property: Event must have valid time range
            $this->assertLessThan($event->getEndTime(), $event->getStartTime(), 
                'Event start time must be before end time');
            
            // Property: Event must be assigned to a creator
            $this->assertNotNull($event->getCreator(), 'Event must have creator');
            
            // Property: Event must be assigned to an office
            $this->assertNotNull($event->getOffice(), 'Event must be assigned to office');
            
            // Property: Event color should match office color or be valid hex
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $event->getColor(), 
                'Event color must be valid hex format');
            
            // Property: Event creator must exist in database
            $creator = $this->entityManager->getRepository(User::class)
                ->find($event->getCreator()->getId());
            $this->assertNotNull($creator, 'Event creator must exist in database');
            
            // Property: Event office must exist in database
            $office = $this->entityManager->getRepository(Office::class)
                ->find($event->getOffice()->getId());
            $this->assertNotNull($office, 'Event office must exist in database');
        }
        
        // Property: Events must have tags
        $tags = $this->entityManager->getRepository(EventTag::class)->findAll();
        $this->assertGreaterThan(0, count($tags), 
            'Seeding should create event tags');
        
        foreach ($tags as $tag) {
            $this->assertNotNull($tag->getName(), 'Tag must have name');
            $this->assertNotNull($tag->getColor(), 'Tag must have color');
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $tag->getColor(), 
                'Tag color must be valid hex format');
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that seeding creates directory contacts with proper office assignments
     */
    public function testSeedingCreatesDirectoryContactsWithProperOfficeAssignments(): void
    {
        // Clear existing data and run seeding
        $this->commandTester->execute(['--clear' => true]);
        
        // Property: Directory contacts must be created
        $contacts = $this->entityManager->getRepository(DirectoryContact::class)->findAll();
        $this->assertGreaterThan(0, count($contacts), 
            'Seeding should create directory contacts');
        
        foreach ($contacts as $contact) {
            // Property: Contact must have required fields
            $this->assertNotNull($contact->getName(), 'Contact must have name');
            $this->assertNotNull($contact->getPosition(), 'Contact must have position');
            $this->assertNotNull($contact->getEmail(), 'Contact must have email');
            $this->assertNotNull($contact->getOffice(), 'Contact must be assigned to office');
            
            // Property: Contact email must be valid format
            $this->assertStringContainsString('@', $contact->getEmail(), 
                'Contact email must be valid format');
            
            // Property: Contact office must exist in database
            $office = $this->entityManager->getRepository(Office::class)
                ->find($contact->getOffice()->getId());
            $this->assertNotNull($office, 'Contact office must exist in database');
        }
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that seeding is repeatable and produces consistent results
     */
    public function testSeedingIsRepeatableAndConsistent(): void
    {
        // First seeding run
        $this->commandTester->execute(['--clear' => true]);
        
        $firstRunOffices = $this->entityManager->getRepository(Office::class)->findAll();
        $firstRunUsers = $this->entityManager->getRepository(User::class)->findAll();
        $firstRunEvents = $this->entityManager->getRepository(Event::class)->findAll();
        
        $firstRunOfficeCodes = array_map(fn($o) => $o->getCode(), $firstRunOffices);
        $firstRunUserEmails = array_map(fn($u) => $u->getEmail(), $firstRunUsers);
        $firstRunEventTitles = array_map(fn($e) => $e->getTitle(), $firstRunEvents);
        
        // Second seeding run
        $this->commandTester->execute(['--clear' => true]);
        
        $secondRunOffices = $this->entityManager->getRepository(Office::class)->findAll();
        $secondRunUsers = $this->entityManager->getRepository(User::class)->findAll();
        $secondRunEvents = $this->entityManager->getRepository(Event::class)->findAll();
        
        $secondRunOfficeCodes = array_map(fn($o) => $o->getCode(), $secondRunOffices);
        $secondRunUserEmails = array_map(fn($u) => $u->getEmail(), $secondRunUsers);
        $secondRunEventTitles = array_map(fn($e) => $e->getTitle(), $secondRunEvents);
        
        // Property: Seeding must produce consistent counts
        $this->assertEquals(count($firstRunOffices), count($secondRunOffices), 
            'Office count must be consistent across seeding runs');
        $this->assertEquals(count($firstRunUsers), count($secondRunUsers), 
            'User count must be consistent across seeding runs');
        $this->assertEquals(count($firstRunEvents), count($secondRunEvents), 
            'Event count must be consistent across seeding runs');
        
        // Property: Seeding must produce same entities
        sort($firstRunOfficeCodes);
        sort($secondRunOfficeCodes);
        $this->assertEquals($firstRunOfficeCodes, $secondRunOfficeCodes, 
            'Office codes must be consistent across seeding runs');
        
        sort($firstRunUserEmails);
        sort($secondRunUserEmails);
        $this->assertEquals($firstRunUserEmails, $secondRunUserEmails, 
            'User emails must be consistent across seeding runs');
        
        sort($firstRunEventTitles);
        sort($secondRunEventTitles);
        $this->assertEquals($firstRunEventTitles, $secondRunEventTitles, 
            'Event titles must be consistent across seeding runs');
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that partial seeding options work correctly
     */
    public function testPartialSeedingOptionsWorkCorrectly(): void
    {
        // Test offices-only seeding
        $this->commandTester->execute(['--clear' => true, '--offices-only' => true]);
        
        $offices = $this->entityManager->getRepository(Office::class)->findAll();
        $users = $this->entityManager->getRepository(User::class)->findAll();
        $events = $this->entityManager->getRepository(Event::class)->findAll();
        
        // Property: Offices-only seeding must create offices but not users or events
        $this->assertGreaterThan(0, count($offices), 
            'Offices-only seeding should create offices');
        $this->assertEquals(0, count($users), 
            'Offices-only seeding should not create users');
        $this->assertEquals(0, count($events), 
            'Offices-only seeding should not create events');
        
        // Test users-only seeding (requires offices to exist first)
        $this->commandTester->execute(['--users-only' => true]);
        
        $usersAfterUserSeeding = $this->entityManager->getRepository(User::class)->findAll();
        $eventsAfterUserSeeding = $this->entityManager->getRepository(Event::class)->findAll();
        
        // Property: Users-only seeding must create users but not additional events
        $this->assertGreaterThan(0, count($usersAfterUserSeeding), 
            'Users-only seeding should create users');
        $this->assertEquals(0, count($eventsAfterUserSeeding), 
            'Users-only seeding should not create events');
        
        // Test events-only seeding (requires users and offices to exist)
        $this->commandTester->execute(['--events-only' => true]);
        
        $eventsAfterEventSeeding = $this->entityManager->getRepository(Event::class)->findAll();
        
        // Property: Events-only seeding must create events
        $this->assertGreaterThan(0, count($eventsAfterEventSeeding), 
            'Events-only seeding should create events');
    }

    /**
     * @test
     * Feature: tesda-calendar-system, Property 15: Database Seeding Consistency
     * 
     * Test that seeding handles existing data appropriately
     */
    public function testSeedingHandlesExistingDataAppropriately(): void
    {
        // First seeding without clear
        $this->commandTester->execute([]);
        
        $firstRunCount = count($this->entityManager->getRepository(Office::class)->findAll());
        
        // Second seeding without clear (should add to existing data)
        $this->commandTester->execute([]);
        
        $secondRunCount = count($this->entityManager->getRepository(Office::class)->findAll());
        
        // Property: Seeding without clear should handle existing data gracefully
        // (Either skip duplicates or handle them appropriately)
        $this->assertGreaterThanOrEqual($firstRunCount, $secondRunCount, 
            'Seeding should handle existing data appropriately');
        
        // Test with clear flag
        $this->commandTester->execute(['--clear' => true]);
        
        $clearedRunCount = count($this->entityManager->getRepository(Office::class)->findAll());
        
        // Property: Seeding with clear should produce consistent count
        $this->assertEquals($firstRunCount, $clearedRunCount, 
            'Seeding with clear should produce consistent results');
    }

    private function clearTestData(): void
    {
        try {
            // Disable foreign key checks temporarily
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Clear in reverse dependency order
            $this->entityManager->createQuery('DELETE FROM App\Entity\Event')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\DirectoryContact')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\EventTag')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\UserProfile')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\User')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
            $this->entityManager->createQuery('DELETE FROM App\Entity\AuditLog')->execute();
            $this->entityManager->flush();
            
            // Re-enable foreign key checks
            $this->entityManager->getConnection()->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        } catch (\Exception $e) {
            // Ignore cleanup errors in tests
        }
    }
}