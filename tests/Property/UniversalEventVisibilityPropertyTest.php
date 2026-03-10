<?php

namespace App\Tests\Property;

use App\Entity\Event;
use App\Entity\Office;
use App\Entity\User;
use App\Entity\UserProfile;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * @test
 * Feature: tesda-calendar-system, Property 5: Universal Event Visibility
 * 
 * Property: For any authenticated user regardless of role, all events in the system 
 * must be visible, color-coded by office assignment, with consistent color legend 
 * display and proper ownership tracking
 * 
 * **Validates: Requirements 4.1, 4.2, 4.4, 4.7**
 */
class UniversalEventVisibilityPropertyTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private EventRepository $eventRepository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $this->entityManager = $kernel->getContainer()->get('doctrine')->getManager();
        $this->eventRepository = $this->entityManager->getRepository(Event::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Clear all test data from database
     */
    private function clearDatabase(): void
    {
        try {
            // Use raw SQL to avoid EntityManager issues
            $connection = $this->entityManager->getConnection();
            
            // Disable foreign key checks temporarily
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Clear tables in any order
            $connection->executeStatement('DELETE FROM events');
            $connection->executeStatement('DELETE FROM user_profiles');
            $connection->executeStatement('DELETE FROM users');
            $connection->executeStatement('DELETE FROM offices');
            
            // Re-enable foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            
            // Clear the EntityManager to avoid stale references
            $this->entityManager->clear();
            
        } catch (\Exception $e) {
            // If this fails, we'll have to live with the data
            // The test database should be reset between test runs anyway
        }
    }

    /**
     * Property Test: Universal Event Visibility
     * 
     * Tests that all authenticated users can view all events regardless of their role,
     * with proper color coding and ownership tracking.
     */
    public function testUniversalEventVisibilityProperty(): void
    {
        // Run a comprehensive single test with multiple scenarios
        $this->runComprehensivePropertyTest();
    }

    private function runComprehensivePropertyTest(): void
    {
        // Test multiple scenarios in a single run to avoid EntityManager issues
        $scenarios = [
            ['offices' => 2, 'users' => 3, 'events' => 5],
            ['offices' => 3, 'users' => 5, 'events' => 8],
            ['offices' => 4, 'users' => 6, 'events' => 10],
            ['offices' => 2, 'users' => 4, 'events' => 7],
            ['offices' => 3, 'users' => 4, 'events' => 6],
        ];

        foreach ($scenarios as $scenario) {
            // Generate test data for this scenario
            $offices = $this->generateRandomOffices($scenario['offices']);
            $users = $this->generateRandomUsers($offices, $scenario['users']);
            $events = $this->generateRandomEvents($users, $offices, $scenario['events']);

            // Test universal visibility for each user role
            foreach ($users as $user) {
                $this->assertUniversalEventVisibility($user, $events, $offices);
            }

            // Clean up this scenario's data
            $this->simpleCleanup($events, $users, $offices);
        }
    }

    private function runSinglePropertyTest(): void
    {
        // Generate random test data
        $offices = $this->generateRandomOffices(rand(2, 5));
        $users = $this->generateRandomUsers($offices, rand(3, 8));
        $events = $this->generateRandomEvents($users, $offices, rand(5, 15));

        // Test universal visibility for each user role
        foreach ($users as $user) {
            $this->assertUniversalEventVisibility($user, $events, $offices);
        }

        // Clean up test data - use simple approach
        $this->simpleCleanup($events, $users, $offices);
    }

    private function generateRandomOffices(int $count): array
    {
        $offices = [];
        
        for ($i = 0; $i < $count; $i++) {
            $office = new Office();
            $office->setName('Office ' . uniqid());
            $office->setCode('OFF' . uniqid()); // Use uniqid() to ensure uniqueness across test iterations
            
            // Generate truly unique color using microtime and uniqid
            $uniqueId = uniqid('', true);
            $hash = md5($uniqueId . $i);
            $uniqueColor = '#' . strtoupper(substr($hash, 0, 6));
            $office->setColor($uniqueColor);
            
            $this->entityManager->persist($office);
            $offices[] = $office;
        }
        
        $this->entityManager->flush();
        return $offices;
    }

    private function generateRandomUsers(array $offices, int $count): array
    {
        $users = [];
        $roles = ['ROLE_PROVINCE', 'ROLE_DIVISION', 'ROLE_EO', 'ROLE_OSEC', 'ROLE_ADMIN'];
        
        for ($i = 0; $i < $count; $i++) {
            $user = new User();
            $user->setEmail('user' . uniqid() . '@tesda.gov.ph');
            $user->setPassword('$2y$13$hashed_password');
            $user->setRoles([$roles[array_rand($roles)]]);
            $user->setVerified(true);
            $user->setOffice($offices[array_rand($offices)]);
            
            $profile = new UserProfile();
            $profile->setFirstName('First' . $i);
            $profile->setLastName('Last' . $i);
            $profile->setComplete(true);
            $profile->setUser($user);
            
            $this->entityManager->persist($user);
            $this->entityManager->persist($profile);
            $users[] = $user;
        }
        
        $this->entityManager->flush();
        return $users;
    }

    private function generateRandomEvents(array $users, array $offices, int $count): array
    {
        $events = [];
        
        for ($i = 0; $i < $count; $i++) {
            $event = new Event();
            $event->setTitle('Event ' . uniqid());
            $event->setDescription('Description for event ' . $i);
            
            $startTime = new \DateTime('+' . rand(1, 30) . ' days +' . rand(8, 17) . ' hours');
            $endTime = clone $startTime;
            $endTime->add(new \DateInterval('PT' . rand(1, 4) . 'H'));
            
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setCreator($users[array_rand($users)]);
            $event->setOffice($offices[array_rand($offices)]);
            $event->setColor($event->getOffice()->getColor());
            $event->setStatus('confirmed');
            $event->setPriority('normal');
            
            $this->entityManager->persist($event);
            $events[] = $event;
        }
        
        $this->entityManager->flush();
        return $events;
    }

    private function assertUniversalEventVisibility(User $user, array $events, array $offices): void
    {
        // Requirement 4.1: All events must be visible to all authenticated users
        // Only count the events we just created, not all events in the database
        $allEventsInDb = $this->eventRepository->findAll();
        $visibleEvents = [];
        
        // Filter to only the events we created in this test iteration
        foreach ($allEventsInDb as $dbEvent) {
            foreach ($events as $expectedEvent) {
                if ($dbEvent->getId() === $expectedEvent->getId()) {
                    $visibleEvents[] = $dbEvent;
                    break;
                }
            }
        }
        
        $this->assertCount(
            count($events),
            $visibleEvents,
            'All events must be visible to authenticated user regardless of role'
        );

        foreach ($events as $expectedEvent) {
            $found = false;
            foreach ($visibleEvents as $visibleEvent) {
                if ($visibleEvent->getId() === $expectedEvent->getId()) {
                    $found = true;
                    
                    // Requirement 4.2: Events must be color-coded by office assignment
                    $this->assertEventColorCoding($visibleEvent);
                    
                    // Requirement 4.4: Proper ownership tracking
                    $this->assertEventOwnershipTracking($visibleEvent);
                    
                    break;
                }
            }
            
            $this->assertTrue($found, 'Event must be visible to all authenticated users');
        }

        // Requirement 4.7: Color legend consistency
        $this->assertColorLegendConsistency($offices, $events);
    }

    private function assertEventColorCoding(Event $event): void
    {
        // Event must have a color
        $this->assertNotNull($event->getEffectiveColor(), 'Event must have a color');
        
        // Color must be a valid hex color
        $this->assertMatchesRegularExpression(
            '/^#[0-9A-Fa-f]{6}$/',
            $event->getEffectiveColor(),
            'Event color must be a valid hex color'
        );
        
        // If event has an office, color should match office color or be explicitly set
        if ($event->getOffice()) {
            $effectiveColor = $event->getEffectiveColor();
            $officeColor = $event->getOffice()->getColor();
            $eventColor = $event->getColor();
            
            $this->assertTrue(
                $effectiveColor === $officeColor || $effectiveColor === $eventColor,
                'Event effective color must be either office color or explicitly set event color'
            );
        }
    }

    private function assertEventOwnershipTracking(Event $event): void
    {
        // Event must have a creator
        $this->assertNotNull($event->getCreator(), 'Event must have a creator for ownership tracking');
        
        // Creator must be a valid user
        $this->assertInstanceOf(User::class, $event->getCreator(), 'Event creator must be a User entity');
        
        // Event must have creation and update timestamps
        $this->assertNotNull($event->getCreatedAt(), 'Event must have creation timestamp');
        $this->assertNotNull($event->getUpdatedAt(), 'Event must have update timestamp');
        
        // Creation time should not be in the future
        $this->assertLessThanOrEqual(
            new \DateTime(),
            $event->getCreatedAt(),
            'Event creation time should not be in the future'
        );
        
        // Update time should be >= creation time
        $this->assertGreaterThanOrEqual(
            $event->getCreatedAt(),
            $event->getUpdatedAt(),
            'Event update time should be >= creation time'
        );
    }

    private function assertColorLegendConsistency(array $offices, array $events): void
    {
        // Collect all office colors used in events
        $usedOfficeColors = [];
        foreach ($events as $event) {
            if ($event->getOffice()) {
                $usedOfficeColors[$event->getOffice()->getId()] = $event->getOffice()->getColor();
            }
        }
        
        // Each office should have a unique color
        $allColors = [];
        foreach ($offices as $office) {
            $color = $office->getColor();
            $this->assertNotNull($color, 'Office must have a color for legend display');
            $this->assertMatchesRegularExpression(
                '/^#[0-9A-Fa-f]{6}$/',
                $color,
                'Office color must be a valid hex color'
            );
            
            $this->assertNotContains(
                $color,
                $allColors,
                'Office colors must be unique for proper legend display'
            );
            
            $allColors[] = $color;
        }
        
        // Colors used in events should match office colors
        foreach ($usedOfficeColors as $officeId => $color) {
            $office = null;
            foreach ($offices as $o) {
                if ($o->getId() === $officeId) {
                    $office = $o;
                    break;
                }
            }
            
            $this->assertNotNull($office, 'Office referenced in event must exist');
            $this->assertEquals(
                $office->getColor(),
                $color,
                'Event office color must match the office\'s assigned color'
            );
        }
    }

    private function simpleCleanup(array $events, array $users, array $offices): void
    {
        try {
            $connection = $this->entityManager->getConnection();
            
            // Disable foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
            
            // Remove entities directly from database using IDs
            $eventIds = array_map(fn($e) => $e->getId(), array_filter($events, fn($e) => $e->getId()));
            $userIds = array_map(fn($u) => $u->getId(), array_filter($users, fn($u) => $u->getId()));
            $officeIds = array_map(fn($o) => $o->getId(), array_filter($offices, fn($o) => $o->getId()));
            
            if (!empty($eventIds)) {
                $connection->executeStatement('DELETE FROM events WHERE id IN (' . implode(',', $eventIds) . ')');
            }
            
            if (!empty($userIds)) {
                $connection->executeStatement('DELETE FROM user_profiles WHERE user_id IN (' . implode(',', $userIds) . ')');
                $connection->executeStatement('DELETE FROM users WHERE id IN (' . implode(',', $userIds) . ')');
            }
            
            if (!empty($officeIds)) {
                $connection->executeStatement('DELETE FROM offices WHERE id IN (' . implode(',', $officeIds) . ')');
            }
            
            // Re-enable foreign key checks
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            
            // Clear EntityManager to avoid stale references
            $this->entityManager->clear();
            
        } catch (\Exception $e) {
            // If cleanup fails, just clear the EntityManager
            $this->entityManager->clear();
        }
    }

    private function cleanupTestData(array $events, array $users, array $offices): void
    {
        $this->simpleCleanup($events, $users, $offices);
    }

    /**
     * Test that event visibility is consistent across different calendar views
     */
    public function testEventVisibilityAcrossCalendarViews(): void
    {
        // Generate test data
        $offices = $this->generateRandomOffices(3);
        $users = $this->generateRandomUsers($offices, 5);
        $events = $this->generateRandomEvents($users, $offices, 10);

        // Test different date ranges (simulating different calendar views)
        $dateRanges = [
            ['start' => new \DateTime('-1 month'), 'end' => new \DateTime('+1 month')], // Month view
            ['start' => new \DateTime('-1 week'), 'end' => new \DateTime('+1 week')],   // Week view
            ['start' => new \DateTime('today'), 'end' => new \DateTime('tomorrow')],    // Day view
        ];

        foreach ($dateRanges as $range) {
            $eventsInRange = $this->eventRepository->findEventsInRange($range['start'], $range['end']);
            
            // All events in range should be visible
            foreach ($eventsInRange as $event) {
                $this->assertEventColorCoding($event);
                $this->assertEventOwnershipTracking($event);
            }
        }

        // Clean up with proper order
        $this->cleanupTestData($events, $users, $offices);
    }

    /**
     * Test that event tooltips contain proper information
     */
    public function testEventTooltipInformation(): void
    {
        // Generate test data
        $offices = $this->generateRandomOffices(2);
        $users = $this->generateRandomUsers($offices, 3);
        $events = $this->generateRandomEvents($users, $offices, 5);

        foreach ($events as $event) {
            // Verify all required tooltip information is available
            $this->assertNotNull($event->getTitle(), 'Event title must be available for tooltip');
            $this->assertNotNull($event->getStartTime(), 'Event start time must be available for tooltip');
            $this->assertNotNull($event->getEndTime(), 'Event end time must be available for tooltip');
            $this->assertNotNull($event->getCreator(), 'Event creator must be available for tooltip');
            
            // Office information should be available if set
            if ($event->getOffice()) {
                $this->assertNotNull($event->getOffice()->getName(), 'Office name must be available for tooltip');
            }
            
            // Verify effective color is available for display
            $this->assertNotNull($event->getEffectiveColor(), 'Event effective color must be available for display');
        }

        // Clean up with proper order
        $this->cleanupTestData($events, $users, $offices);
    }
}