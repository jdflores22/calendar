<?php

namespace App\Tests\Property;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\Office;
use App\Entity\EventTag;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @test
 * Feature: tesda-calendar-system, Property 8: Event Data Integrity
 * Validates: Requirements 5.4, 5.5, 5.6, 5.9
 */
class EventDataIntegrityPropertyTest extends KernelTestCase
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
        $this->entityManager->createQuery('DELETE FROM App\Entity\Office')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\EventTag')->execute();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
    }

    /**
     * Property 8: Event Data Integrity
     * For any event operation (create, update, delete), all event fields 
     * (title, start/end time, location, description, tags, office, color, attachments) 
     * must be validated, and drag-and-drop or resize operations must correctly update event timing
     * 
     * Validates: Requirements 5.4, 5.5, 5.6, 5.9
     */
    public function testEventDataIntegrity(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'Event_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\choose(1, 23), // Start hour
            Generator\choose(0, 59), // Start minute
            Generator\choose(1, 4),  // Duration in hours
            Generator\map(
                function($str) { return 'Location_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\elements(['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF'])
        )->then(function (string $title, int $startHour, int $startMinute, int $durationHours, string $location, string $color) {
            // Create a user first
            $user = new User();
            $user->setEmail('test' . abs(crc32($title)) . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create an office
            $office = new Office();
            $office->setName('Office_' . abs(crc32($title)));
            $office->setCode('OFF' . abs(crc32($title)) % 1000);
            $office->setColor($color);
            
            $this->entityManager->persist($office);
            $this->entityManager->flush();

            // Create event with valid time range
            $startTime = new \DateTime('today');
            $startTime->setTime($startHour, $startMinute);
            $endTime = clone $startTime;
            $endTime->add(new \DateInterval("PT{$durationHours}H"));

            $event = new Event();
            $event->setTitle($title);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setLocation($location);
            $event->setColor($color);
            $event->setCreator($user);
            $event->setOffice($office);

            // Property: Event must have valid time range
            $this->assertTrue($event->isValidTimeRange(), 'Event must have valid time range (end after start)');

            // Property: Event duration calculation must be correct
            $expectedDurationMinutes = $durationHours * 60;
            $this->assertEquals($expectedDurationMinutes, $event->getDurationInMinutes(), 
                'Event duration calculation must be correct');

            // Property: Color formatting must be consistent
            $this->assertStringStartsWith('#', $event->getColor(), 'Event color must start with #');
            $this->assertEquals(7, strlen($event->getColor()), 'Event color must be 7 characters long');
            $this->assertMatchesRegularExpression('/^#[0-9A-F]{6}$/', $event->getColor(), 
                'Event color must be uppercase hex format');

            // Property: Event effective color should fall back to office color
            $this->assertEquals($color, $event->getEffectiveColor(), 
                'Event effective color should match assigned color');

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Property: Event must be retrievable from database
            $foundEvent = $this->entityManager->find(Event::class, $event->getId());
            $this->assertNotNull($foundEvent, 'Event must be retrievable from database');
            $this->assertEquals($title, $foundEvent->getTitle(), 'Event title must be preserved');
            $this->assertEquals($startTime, $foundEvent->getStartTime(), 'Event start time must be preserved');
            $this->assertEquals($endTime, $foundEvent->getEndTime(), 'Event end time must be preserved');
            $this->assertEquals($location, $foundEvent->getLocation(), 'Event location must be preserved');

            // Property: Event relationships must be maintained
            $this->assertSame($user, $foundEvent->getCreator(), 'Event creator relationship must be maintained');
            $this->assertSame($office, $foundEvent->getOffice(), 'Event office relationship must be maintained');

            // Test drag-and-drop simulation (time update)
            $newStartTime = clone $startTime;
            $newStartTime->add(new \DateInterval('PT1H')); // Move 1 hour later
            $newEndTime = clone $endTime;
            $newEndTime->add(new \DateInterval('PT1H'));

            $foundEvent->setStartTime($newStartTime);
            $foundEvent->setEndTime($newEndTime);

            // Property: After drag-and-drop, time range must still be valid
            $this->assertTrue($foundEvent->isValidTimeRange(), 
                'After drag-and-drop, event must still have valid time range');

            // Property: Duration should remain the same after drag-and-drop
            $this->assertEquals($expectedDurationMinutes, $foundEvent->getDurationInMinutes(), 
                'Duration should remain the same after drag-and-drop');

            $this->entityManager->flush();

            // Property: Updated times must be persisted correctly
            $this->entityManager->refresh($foundEvent);
            $this->assertEquals($newStartTime, $foundEvent->getStartTime(), 
                'Updated start time must be persisted');
            $this->assertEquals($newEndTime, $foundEvent->getEndTime(), 
                'Updated end time must be persisted');

            // Test resize simulation (end time update)
            $resizedEndTime = clone $newEndTime;
            $resizedEndTime->add(new \DateInterval('PT30M')); // Extend by 30 minutes
            $foundEvent->setEndTime($resizedEndTime);

            // Property: After resize, time range must still be valid
            $this->assertTrue($foundEvent->isValidTimeRange(), 
                'After resize, event must still have valid time range');

            // Property: Duration should be updated after resize
            $newExpectedDuration = $expectedDurationMinutes + 30; // Added 30 minutes
            $this->assertEquals($newExpectedDuration, $foundEvent->getDurationInMinutes(), 
                'Duration should be updated after resize');

            // Clean up
            $this->entityManager->remove($foundEvent);
            $this->entityManager->remove($office);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Event Tag Management Integrity
     * Event tags must be properly managed and maintained
     */
    public function testEventTagIntegrity(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'Event_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\vector(3, Generator\map(
                function($str) { return 'Tag_' . abs(crc32($str)); },
                Generator\string()
            ))
        )->then(function (string $title, array $tagNames) {
            // Skip if tag names are not unique
            if (count($tagNames) !== count(array_unique($tagNames))) {
                return;
            }

            $timestamp = microtime(true) * 1000000; // Microsecond precision for unique emails

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . abs(crc32($title)) . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create event
            $startTime = new \DateTime('today 10:00');
            $endTime = new \DateTime('today 11:00');

            $event = new Event();
            $event->setTitle($title);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setColor('#FF0000');
            $event->setCreator($user);

            // Create and add tags
            $tags = [];
            foreach ($tagNames as $tagName) {
                $tag = new EventTag();
                $tag->setName($tagName);
                $this->entityManager->persist($tag);
                $tags[] = $tag;
                $event->addTag($tag);
            }

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Refresh entities to ensure bidirectional relationships are loaded
            $this->entityManager->refresh($event);
            foreach ($tags as $tag) {
                $this->entityManager->refresh($tag);
            }

            // Property: Event must have all assigned tags
            $this->assertCount(count($tagNames), $event->getTags(), 
                'Event must have all assigned tags');

            // Property: Tag names must be retrievable
            $retrievedTagNames = $event->getTagNames();
            foreach ($tagNames as $tagName) {
                $this->assertContains($tagName, $retrievedTagNames, 
                    'Event must contain assigned tag name');
                $this->assertTrue($event->hasTag($tagName), 
                    'Event hasTag() method must work correctly');
            }

            // Property: Bidirectional relationship must be maintained
            foreach ($tags as $tag) {
                $this->assertTrue($tag->getEvents()->contains($event), 
                    'Tag must reference the event in bidirectional relationship');
            }

            // Test tag removal
            $firstTag = $tags[0];
            $event->removeTag($firstTag);

            // Property: After removal, tag should not be associated with event
            $this->assertFalse($event->getTags()->contains($firstTag), 
                'After removal, event should not contain the tag');
            $this->assertFalse($event->hasTag($firstTag->getName()), 
                'After removal, hasTag() should return false');

            // Clean up
            $this->entityManager->remove($event);
            foreach ($tags as $tag) {
                $this->entityManager->remove($tag);
            }
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Event Conflict Detection
     * Events must correctly detect conflicts with other events
     */
    public function testEventConflictDetection(): void
    {
        $this->limitTo(3)->forAll(
            Generator\choose(9, 15), // Start hour for first event
            Generator\choose(1, 3),  // Duration for first event
            Generator\choose(-2, 4)  // Offset for second event start (negative = before, positive = after)
        )->then(function (int $startHour1, int $duration1, int $offsetHours) {
            $timestamp = microtime(true) * 1000000; // Microsecond precision for unique emails

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . time() . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create first event
            $startTime1 = new \DateTime('today');
            $startTime1->setTime($startHour1, 0);
            $endTime1 = clone $startTime1;
            $endTime1->add(new \DateInterval("PT{$duration1}H"));

            $event1 = new Event();
            $event1->setTitle('Event 1');
            $event1->setStartTime($startTime1);
            $event1->setEndTime($endTime1);
            $event1->setColor('#FF0000');
            $event1->setCreator($user);

            // Create second event with offset - handle negative offsets properly
            $startTime2 = clone $startTime1;
            if ($offsetHours >= 0) {
                $startTime2->add(new \DateInterval("PT{$offsetHours}H"));
            } else {
                $startTime2->sub(new \DateInterval("PT" . abs($offsetHours) . "H"));
            }
            $endTime2 = clone $startTime2;
            $endTime2->add(new \DateInterval("PT{$duration1}H"));

            $event2 = new Event();
            $event2->setTitle('Event 2');
            $event2->setStartTime($startTime2);
            $event2->setEndTime($endTime2);
            $event2->setColor('#00FF00');
            $event2->setCreator($user);

            // Property: Conflict detection must work correctly
            $shouldConflict = $startTime2 < $endTime1 && $endTime2 > $startTime1;
            $actualConflict = $event1->conflictsWith($event2);
            
            $this->assertEquals($shouldConflict, $actualConflict, 
                'Event conflict detection must work correctly');

            // Property: Conflict detection must be symmetric
            $this->assertEquals($event1->conflictsWith($event2), $event2->conflictsWith($event1), 
                'Conflict detection must be symmetric');

            // Clean up
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Event Status and Priority Management
     * Event status and priority must be properly validated and maintained
     */
    public function testEventStatusAndPriorityManagement(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['confirmed', 'tentative', 'cancelled']),
            Generator\elements(['low', 'normal', 'high', 'urgent'])
        )->then(function (string $status, string $priority) {
            $timestamp = microtime(true) * 1000000; // Microsecond precision for unique emails

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . time() . rand() . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create event
            $startTime = new \DateTime('today 10:00');
            $endTime = new \DateTime('today 11:00');

            $event = new Event();
            $event->setTitle('Test Event');
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setColor('#FF0000');
            $event->setCreator($user);
            $event->setStatus($status);
            $event->setPriority($priority);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Property: Status and priority must be preserved
            $foundEvent = $this->entityManager->find(Event::class, $event->getId());
            $this->assertEquals($status, $foundEvent->getStatus(), 'Event status must be preserved');
            $this->assertEquals($priority, $foundEvent->getPriority(), 'Event priority must be preserved');

            // Property: Default values must be set correctly
            $defaultEvent = new Event();
            $this->assertEquals('confirmed', $defaultEvent->getStatus(), 'Default status should be confirmed');
            $this->assertEquals('normal', $defaultEvent->getPriority(), 'Default priority should be normal');

            // Clean up
            $this->entityManager->remove($foundEvent);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }
}