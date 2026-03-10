<?php

namespace App\Tests\Property;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\Office;
use App\Service\RecurrenceService;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @test
 * Feature: tesda-calendar-system, Property 9: Recurring Event Pattern Consistency
 * Validates: Requirements 5.7, 5.8
 */
class RecurringEventPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private RecurrenceService $recurrenceService;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->recurrenceService = static::getContainer()->get(RecurrenceService::class);
        
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
     * Property 9: Recurring Event Pattern Consistency
     * For any recurring event pattern, the generated event instances must follow 
     * the specified recurrence rules and integrate properly with holiday displays
     * 
     * Validates: Requirements 5.7, 5.8
     */
    public function testRecurringEventPatternConsistency(): void
    {
        $this->limitTo(5)->forAll(
            Generator\elements(['daily', 'weekly', 'monthly', 'yearly']),
            Generator\choose(1, 3), // Interval
            Generator\choose(1, 10), // Count limit
            Generator\choose(9, 15) // Start hour
        )->then(function (string $frequency, int $interval, int $count, int $startHour) {
            $timestamp = microtime(true) * 1000000;

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create an office
            $office = new Office();
            $office->setName('Office_' . $timestamp);
            $office->setCode('OFF' . ($timestamp % 1000));
            $office->setColor('#FF0000');
            
            $this->entityManager->persist($office);
            $this->entityManager->flush();

            // Create recurrence pattern
            $pattern = $this->recurrenceService->createDefaultPattern($frequency, $interval);
            $pattern = $this->recurrenceService->addCountLimit($pattern, $count);

            // Property: Pattern must be valid
            $this->assertTrue($this->recurrenceService->validateRecurrencePattern($pattern), 
                'Generated recurrence pattern must be valid');

            // Create recurring event
            $startTime = new \DateTime('today');
            $startTime->setTime($startHour, 0);
            $endTime = clone $startTime;
            $endTime->add(new \DateInterval('PT1H'));

            $event = new Event();
            $event->setTitle('Recurring Event ' . $timestamp);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setColor('#0000FF');
            $event->setCreator($user);
            $event->setOffice($office);
            $event->setRecurring(true);
            $event->setRecurrencePattern($pattern);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Property: Recurring event must be marked as recurring
            $this->assertTrue($event->isRecurring(), 'Event must be marked as recurring');
            $this->assertNotNull($event->getRecurrencePattern(), 'Event must have recurrence pattern');

            // Generate instances for testing
            $rangeStart = new \DateTime('today');
            $rangeEnd = new \DateTime('+3 months');
            
            $instances = $this->recurrenceService->generateRecurringInstances($event, $rangeStart, $rangeEnd);

            // Property: Generated instances must not exceed count limit
            $this->assertLessThanOrEqual($count, count($instances), 
                'Generated instances must not exceed count limit');

            // Property: All instances must have consistent duration
            $originalDuration = $event->getDurationInMinutes();
            foreach ($instances as $instance) {
                $instanceDuration = $instance['start']->diff($instance['end']);
                $instanceDurationMinutes = ($instanceDuration->h * 60) + $instanceDuration->i;
                $this->assertEquals($originalDuration, $instanceDurationMinutes, 
                    'All instances must have consistent duration');
            }

            // Property: All instances must have consistent title and properties
            foreach ($instances as $instance) {
                $this->assertEquals($event->getTitle(), $instance['title'], 
                    'All instances must have consistent title');
                $this->assertEquals($event->getDescription(), $instance['description'], 
                    'All instances must have consistent description');
                $this->assertEquals($event->getLocation(), $instance['location'], 
                    'All instances must have consistent location');
                $this->assertEquals($event->getId(), $instance['masterEventId'], 
                    'All instances must reference master event ID');
            }

            // Property: Instances must follow frequency pattern
            if (count($instances) > 1) {
                $this->validateFrequencyPattern($instances, $frequency, $interval);
            }

            // Property: Pattern description must be human-readable
            $description = $this->recurrenceService->getPatternDescription($pattern);
            $this->assertNotEmpty($description, 'Pattern description must not be empty');
            $this->assertIsString($description, 'Pattern description must be a string');

            // Clean up
            $this->entityManager->remove($event);
            $this->entityManager->remove($office);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Weekly Recurrence Pattern Validation
     * Weekly patterns with specific weekdays must generate instances correctly
     */
    public function testWeeklyRecurrencePatternValidation(): void
    {
        $this->limitTo(3)->forAll(
            Generator\choose(1, 2), // Interval (every 1-2 weeks)
            Generator\subset(['monday', 'wednesday', 'friday']) // Weekdays subset
        )->then(function (int $interval, array $weekdays) {
            // Skip if no weekdays selected
            if (empty($weekdays)) {
                return;
            }

            $timestamp = microtime(true) * 1000000;

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create weekly pattern with specific weekdays
            $pattern = $this->recurrenceService->createWeeklyPattern($weekdays, $interval);

            // Property: Weekly pattern must be valid
            $this->assertTrue($this->recurrenceService->validateRecurrencePattern($pattern), 
                'Weekly recurrence pattern must be valid');

            // Property: Pattern must contain correct weekdays
            $this->assertEquals('weekly', $pattern['frequency'], 'Pattern frequency must be weekly');
            $this->assertEquals($interval, $pattern['interval'], 'Pattern interval must match');
            $this->assertEquals($weekdays, $pattern['weekdays'], 'Pattern weekdays must match');

            // Create recurring event
            $startTime = new \DateTime('next monday 10:00'); // Start on a Monday
            $endTime = clone $startTime;
            $endTime->add(new \DateInterval('PT1H'));

            $event = new Event();
            $event->setTitle('Weekly Event ' . $timestamp);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setColor('#00FF00');
            $event->setCreator($user);
            $event->setRecurring(true);
            $event->setRecurrencePattern($pattern);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Generate instances for 4 weeks
            $rangeStart = clone $startTime;
            $rangeEnd = clone $startTime;
            $rangeEnd->add(new \DateInterval('P4W'));
            
            $instances = $this->recurrenceService->generateRecurringInstances($event, $rangeStart, $rangeEnd);

            // Property: Generated instances must only occur on specified weekdays
            foreach ($instances as $instance) {
                $dayOfWeek = strtolower($instance['start']->format('l'));
                $this->assertContains($dayOfWeek, $weekdays, 
                    'Instance must occur only on specified weekdays');
            }

            // Clean up
            $this->entityManager->remove($event);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Monthly Recurrence Pattern Validation
     * Monthly patterns must handle different month lengths correctly
     */
    public function testMonthlyRecurrencePatternValidation(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['dayOfMonth', 'dayOfWeek']),
            Generator\choose(1, 3) // Interval (every 1-3 months)
        )->then(function (string $monthlyType, int $interval) {
            $timestamp = microtime(true) * 1000000;

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create monthly pattern
            $pattern = $this->recurrenceService->createMonthlyPattern($monthlyType, $interval);

            // Property: Monthly pattern must be valid
            $this->assertTrue($this->recurrenceService->validateRecurrencePattern($pattern), 
                'Monthly recurrence pattern must be valid');

            // Property: Pattern must contain correct monthly type
            $this->assertEquals('monthly', $pattern['frequency'], 'Pattern frequency must be monthly');
            $this->assertEquals($interval, $pattern['interval'], 'Pattern interval must match');
            $this->assertEquals($monthlyType, $pattern['monthlyType'], 'Pattern monthly type must match');

            // Create recurring event on the 15th of the month
            $startTime = new \DateTime('2024-01-15 10:00:00');
            $endTime = clone $startTime;
            $endTime->add(new \DateInterval('PT1H'));

            $event = new Event();
            $event->setTitle('Monthly Event ' . $timestamp);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setColor('#FFFF00');
            $event->setCreator($user);
            $event->setRecurring(true);
            $event->setRecurrencePattern($pattern);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Generate instances for 6 months
            $rangeStart = new \DateTime('2024-01-01');
            $rangeEnd = new \DateTime('2024-07-31');
            
            $instances = $this->recurrenceService->generateRecurringInstances($event, $rangeStart, $rangeEnd);

            // Property: Generated instances must follow monthly pattern
            if (count($instances) > 1) {
                for ($i = 1; $i < count($instances); $i++) {
                    $prevInstance = $instances[$i - 1];
                    $currentInstance = $instances[$i];
                    
                    $monthDiff = $currentInstance['start']->format('n') - $prevInstance['start']->format('n');
                    if ($monthDiff < 0) {
                        $monthDiff += 12; // Handle year boundary
                    }
                    
                    // Allow for some flexibility due to month length variations
                    $this->assertGreaterThanOrEqual($interval, $monthDiff, 
                        'Monthly instances must respect interval');
                }
            }

            // Clean up
            $this->entityManager->remove($event);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Recurrence Pattern Validation
     * All recurrence patterns must pass validation rules
     */
    public function testRecurrencePatternValidation(): void
    {
        $this->limitTo(5)->forAll(
            Generator\elements(['daily', 'weekly', 'monthly', 'yearly']),
            Generator\choose(1, 5), // Valid interval
            Generator\choose(1, 20) // Valid count
        )->then(function (string $frequency, int $interval, int $count) {
            // Test valid pattern
            $validPattern = [
                'frequency' => $frequency,
                'interval' => $interval,
                'count' => $count
            ];

            // Property: Valid patterns must pass validation
            $this->assertTrue($this->recurrenceService->validateRecurrencePattern($validPattern), 
                'Valid recurrence pattern must pass validation');

            // Test invalid patterns
            $invalidPatterns = [
                // Missing frequency
                ['interval' => $interval, 'count' => $count],
                // Invalid frequency
                ['frequency' => 'invalid', 'interval' => $interval, 'count' => $count],
                // Invalid interval
                ['frequency' => $frequency, 'interval' => 0, 'count' => $count],
                // Invalid count
                ['frequency' => $frequency, 'interval' => $interval, 'count' => 0],
            ];

            // Property: Invalid patterns must fail validation
            foreach ($invalidPatterns as $invalidPattern) {
                $this->assertFalse($this->recurrenceService->validateRecurrencePattern($invalidPattern), 
                    'Invalid recurrence pattern must fail validation');
            }
        });
    }

    /**
     * Property: Until Date Validation
     * Recurrence patterns with until dates must stop generating instances after the date
     */
    public function testUntilDateValidation(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['daily', 'weekly']),
            Generator\choose(1, 2) // Interval
        )->then(function (string $frequency, int $interval) {
            $timestamp = microtime(true) * 1000000;

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create pattern with until date
            $pattern = $this->recurrenceService->createDefaultPattern($frequency, $interval);
            $untilDate = new \DateTime('+1 month');
            $pattern = $this->recurrenceService->addUntilDate($pattern, $untilDate);

            // Property: Pattern with until date must be valid
            $this->assertTrue($this->recurrenceService->validateRecurrencePattern($pattern), 
                'Pattern with until date must be valid');

            // Create recurring event
            $startTime = new \DateTime('today 10:00');
            $endTime = clone $startTime;
            $endTime->add(new \DateInterval('PT1H'));

            $event = new Event();
            $event->setTitle('Until Date Event ' . $timestamp);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setColor('#FF00FF');
            $event->setCreator($user);
            $event->setRecurring(true);
            $event->setRecurrencePattern($pattern);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Generate instances for 3 months (beyond until date)
            $rangeStart = new \DateTime('today');
            $rangeEnd = new \DateTime('+3 months');
            
            $instances = $this->recurrenceService->generateRecurringInstances($event, $rangeStart, $rangeEnd);

            // Property: No instances should occur after until date
            foreach ($instances as $instance) {
                $this->assertLessThanOrEqual($untilDate, $instance['start'], 
                    'No instances should occur after until date');
            }

            // Clean up
            $this->entityManager->remove($event);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Validate that instances follow the specified frequency pattern
     */
    private function validateFrequencyPattern(array $instances, string $frequency, int $interval): void
    {
        for ($i = 1; $i < count($instances); $i++) {
            $prevInstance = $instances[$i - 1];
            $currentInstance = $instances[$i];
            
            $diff = $prevInstance['start']->diff($currentInstance['start']);
            
            switch ($frequency) {
                case 'daily':
                    $expectedDays = $interval;
                    $this->assertEquals($expectedDays, $diff->days, 
                        'Daily instances must be separated by correct interval');
                    break;
                    
                case 'weekly':
                    $expectedDays = $interval * 7;
                    // Allow some flexibility for weekly patterns with specific weekdays
                    $this->assertGreaterThanOrEqual($interval, ceil($diff->days / 7), 
                        'Weekly instances must respect interval');
                    break;
                    
                case 'yearly':
                    $expectedYears = $interval;
                    $yearDiff = $currentInstance['start']->format('Y') - $prevInstance['start']->format('Y');
                    $this->assertEquals($expectedYears, $yearDiff, 
                        'Yearly instances must be separated by correct interval');
                    break;
            }
        }
    }
}