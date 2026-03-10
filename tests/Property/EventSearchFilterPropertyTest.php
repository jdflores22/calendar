<?php

namespace App\Tests\Property;

use App\Entity\Event;
use App\Entity\User;
use App\Entity\Office;
use App\Entity\EventTag;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Eris\Generator;
use Eris\TestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * @test
 * Feature: tesda-calendar-system, Property 6: Event Search and Filtering Consistency
 * Validates: Requirements 4.5, 4.6, 9.3
 */
class EventSearchFilterPropertyTest extends KernelTestCase
{
    use TestTrait;

    private EntityManagerInterface $entityManager;
    private EventRepository $eventRepository;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->eventRepository = static::getContainer()->get(EventRepository::class);
        
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
     * Property 6: Event Search and Filtering Consistency
     * For any search query or filter criteria, the results must be relevant, 
     * properly formatted with tooltips, and maintain chronological ordering for upcoming events
     * 
     * Validates: Requirements 4.5, 4.6, 9.3
     */
    public function testEventSearchConsistency(): void
    {
        $this->limitTo(5)->forAll(
            Generator\map(
                function($str) { return 'Event_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'Location_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\map(
                function($str) { return 'Description_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\elements(['low', 'normal', 'high', 'urgent']),
            Generator\elements(['confirmed', 'tentative', 'cancelled'])
        )->then(function (string $title, string $location, string $description, string $priority, string $status) {
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

            // Create event
            $startTime = new \DateTime('today 10:00');
            $endTime = new \DateTime('today 11:00');

            $event = new Event();
            $event->setTitle($title);
            $event->setStartTime($startTime);
            $event->setEndTime($endTime);
            $event->setLocation($location);
            $event->setDescription($description);
            $event->setColor('#0000FF');
            $event->setCreator($user);
            $event->setOffice($office);
            $event->setPriority($priority);
            $event->setStatus($status);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Property: Search by title must return relevant results
            $titleSearchResults = $this->eventRepository->searchEvents($title);
            $this->assertContains($event, $titleSearchResults, 
                'Search by title must return the event');

            // Property: Search by partial title must work
            $partialTitle = substr($title, 0, min(5, strlen($title)));
            if (strlen($partialTitle) > 2) {
                $partialSearchResults = $this->eventRepository->searchEvents($partialTitle);
                $this->assertContains($event, $partialSearchResults, 
                    'Search by partial title must return the event');
            }

            // Property: Search by description must return relevant results
            $descriptionSearchResults = $this->eventRepository->searchEvents($description);
            $this->assertContains($event, $descriptionSearchResults, 
                'Search by description must return the event');

            // Property: Advanced search with multiple criteria must work
            $advancedCriteria = [
                'query' => $title,
                'office_ids' => [$office->getId()],
                'priority' => $priority,
                'status' => $status
            ];
            
            $advancedResults = $this->eventRepository->searchEventsAdvanced($advancedCriteria);
            $this->assertContains($event, $advancedResults, 
                'Advanced search with multiple criteria must return the event');

            // Property: Filter by office must return only events from that office
            $officeCriteria = ['office_ids' => [$office->getId()]];
            $officeResults = $this->eventRepository->searchEventsAdvanced($officeCriteria);
            
            foreach ($officeResults as $result) {
                $this->assertEquals($office->getId(), $result->getOffice()->getId(), 
                    'Office filter must return only events from specified office');
            }

            // Property: Filter by priority must return only events with that priority
            $priorityCriteria = ['priority' => $priority];
            $priorityResults = $this->eventRepository->searchEventsAdvanced($priorityCriteria);
            
            foreach ($priorityResults as $result) {
                $this->assertEquals($priority, $result->getPriority(), 
                    'Priority filter must return only events with specified priority');
            }

            // Property: Filter by status must return only events with that status
            $statusCriteria = ['status' => $status];
            $statusResults = $this->eventRepository->searchEventsAdvanced($statusCriteria);
            
            foreach ($statusResults as $result) {
                $this->assertEquals($status, $result->getStatus(), 
                    'Status filter must return only events with specified status');
            }

            // Property: Date range filter must respect boundaries
            $dateRangeCriteria = [
                'start_date' => $startTime->format('Y-m-d'),
                'end_date' => $endTime->format('Y-m-d')
            ];
            
            $dateRangeResults = $this->eventRepository->searchEventsAdvanced($dateRangeCriteria);
            $this->assertContains($event, $dateRangeResults, 
                'Date range filter must include events within the range');

            // Property: Results must be ordered chronologically
            $allResults = $this->eventRepository->searchEventsAdvanced([]);
            $this->assertChronologicalOrder($allResults, 
                'Search results must maintain chronological ordering');

            // Clean up
            $this->entityManager->remove($event);
            $this->entityManager->remove($office);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Tag-based Search and Filtering
     * Events must be searchable and filterable by tags correctly
     */
    public function testTagBasedSearchFiltering(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'Event_' . abs(crc32($str)); },
                Generator\string()
            ),
            Generator\vector(2, Generator\map(
                function($str) { return 'Tag_' . abs(crc32($str)); },
                Generator\string()
            ))
        )->then(function (string $title, array $tagNames) {
            // Skip if tag names are not unique
            if (count($tagNames) !== count(array_unique($tagNames))) {
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

            // Property: Filter by tags must return events with those tags
            $tagCriteria = ['tags' => $tagNames];
            $tagResults = $this->eventRepository->searchEventsAdvanced($tagCriteria);
            
            $this->assertContains($event, $tagResults, 
                'Tag filter must return events with specified tags');

            // Property: Filter by single tag must work
            $singleTagCriteria = ['tags' => [$tagNames[0]]];
            $singleTagResults = $this->eventRepository->searchEventsAdvanced($singleTagCriteria);
            
            $this->assertContains($event, $singleTagResults, 
                'Single tag filter must return events with that tag');

            // Property: Events returned by tag filter must actually have the tags
            foreach ($tagResults as $result) {
                $resultTagNames = $result->getTagNames();
                $hasMatchingTag = false;
                foreach ($tagNames as $searchTag) {
                    if (in_array($searchTag, $resultTagNames, true)) {
                        $hasMatchingTag = true;
                        break;
                    }
                }
                $this->assertTrue($hasMatchingTag, 
                    'Events returned by tag filter must have at least one matching tag');
            }

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
     * Property: Date Range Filtering Accuracy
     * Date range filters must accurately include/exclude events
     */
    public function testDateRangeFilteringAccuracy(): void
    {
        $this->limitTo(3)->forAll(
            Generator\choose(1, 10), // Days offset for event
            Generator\choose(1, 5),  // Days for range start
            Generator\choose(6, 15)  // Days for range end
        )->then(function (int $eventDayOffset, int $rangeStartDays, int $rangeEndDays) {
            $timestamp = microtime(true) * 1000000;

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create event at specific date
            $eventDate = new \DateTime('today');
            $eventDate->add(new \DateInterval("P{$eventDayOffset}D"));
            $eventStart = clone $eventDate;
            $eventStart->setTime(10, 0);
            $eventEnd = clone $eventDate;
            $eventEnd->setTime(11, 0);

            $event = new Event();
            $event->setTitle('Test Event ' . $timestamp);
            $event->setStartTime($eventStart);
            $event->setEndTime($eventEnd);
            $event->setColor('#00FF00');
            $event->setCreator($user);

            $this->entityManager->persist($event);
            $this->entityManager->flush();

            // Create date range
            $rangeStart = new \DateTime('today');
            $rangeStart->add(new \DateInterval("P{$rangeStartDays}D"));
            $rangeEnd = new \DateTime('today');
            $rangeEnd->add(new \DateInterval("P{$rangeEndDays}D"));

            // Property: Event within range must be included
            $criteria = [
                'start_date' => $rangeStart->format('Y-m-d'),
                'end_date' => $rangeEnd->format('Y-m-d')
            ];
            
            $results = $this->eventRepository->searchEventsAdvanced($criteria);
            
            $eventInRange = $eventDate >= $rangeStart && $eventDate <= $rangeEnd;
            
            if ($eventInRange) {
                $this->assertContains($event, $results, 
                    'Event within date range must be included in results');
            } else {
                $this->assertNotContains($event, $results, 
                    'Event outside date range must not be included in results');
            }

            // Property: All returned events must be within the specified range
            foreach ($results as $result) {
                $resultDate = $result->getStartTime();
                $this->assertGreaterThanOrEqual($rangeStart->format('Y-m-d'), $resultDate->format('Y-m-d'), 
                    'All results must be after or on range start date');
                $this->assertLessThanOrEqual($rangeEnd->format('Y-m-d'), $resultDate->format('Y-m-d'), 
                    'All results must be before or on range end date');
            }

            // Clean up
            $this->entityManager->remove($event);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Search Result Relevance and Ordering
     * Search results must be relevant and properly ordered
     */
    public function testSearchResultRelevanceAndOrdering(): void
    {
        $this->limitTo(3)->forAll(
            Generator\map(
                function($str) { return 'CommonWord_' . abs(crc32($str)); },
                Generator\string()
            )
        )->then(function (string $commonWord) {
            $timestamp = microtime(true) * 1000000;

            // Create a user with unique email
            $user = new User();
            $user->setEmail('test' . $timestamp . '@example.com');
            $user->setPassword('password');
            $user->setVerified(true);
            
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Create multiple events with different relevance to search term
            $events = [];
            
            // High relevance: title starts with search term
            $highRelevanceEvent = new Event();
            $highRelevanceEvent->setTitle($commonWord . ' High Relevance');
            $highRelevanceEvent->setStartTime(new \DateTime('today 09:00'));
            $highRelevanceEvent->setEndTime(new \DateTime('today 10:00'));
            $highRelevanceEvent->setColor('#FF0000');
            $highRelevanceEvent->setCreator($user);
            $events[] = $highRelevanceEvent;

            // Medium relevance: search term in description
            $mediumRelevanceEvent = new Event();
            $mediumRelevanceEvent->setTitle('Medium Relevance Event');
            $mediumRelevanceEvent->setDescription('This event contains ' . $commonWord . ' in description');
            $mediumRelevanceEvent->setStartTime(new \DateTime('today 11:00'));
            $mediumRelevanceEvent->setEndTime(new \DateTime('today 12:00'));
            $mediumRelevanceEvent->setColor('#00FF00');
            $mediumRelevanceEvent->setCreator($user);
            $events[] = $mediumRelevanceEvent;

            // Low relevance: search term in location
            $lowRelevanceEvent = new Event();
            $lowRelevanceEvent->setTitle('Low Relevance Event');
            $lowRelevanceEvent->setLocation($commonWord . ' Building');
            $lowRelevanceEvent->setStartTime(new \DateTime('today 13:00'));
            $lowRelevanceEvent->setEndTime(new \DateTime('today 14:00'));
            $lowRelevanceEvent->setColor('#0000FF');
            $lowRelevanceEvent->setCreator($user);
            $events[] = $lowRelevanceEvent;

            foreach ($events as $event) {
                $this->entityManager->persist($event);
            }
            $this->entityManager->flush();

            // Property: Search must return all relevant events
            $searchResults = $this->eventRepository->searchEvents($commonWord);
            
            foreach ($events as $event) {
                $this->assertContains($event, $searchResults, 
                    'Search must return all events containing the search term');
            }

            // Property: Results must be ordered chronologically
            $this->assertChronologicalOrder($searchResults, 
                'Search results must maintain chronological ordering');

            // Property: Empty search must return all events
            $allResults = $this->eventRepository->searchEvents('');
            $this->assertGreaterThanOrEqual(count($events), count($allResults), 
                'Empty search must return all events');

            // Property: Non-matching search must return empty results
            $noMatchResults = $this->eventRepository->searchEvents('NonExistentSearchTerm' . $timestamp);
            $this->assertEmpty($noMatchResults, 
                'Non-matching search must return empty results');

            // Clean up
            foreach ($events as $event) {
                $this->entityManager->remove($event);
            }
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Property: Combined Filter Criteria Accuracy
     * Multiple filter criteria must work together correctly
     */
    public function testCombinedFilterCriteriaAccuracy(): void
    {
        $this->limitTo(3)->forAll(
            Generator\elements(['low', 'normal', 'high', 'urgent']),
            Generator\elements(['confirmed', 'tentative', 'cancelled']),
            Generator\bool()
        )->then(function (string $priority, string $status, bool $isAllDay) {
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
            $office->setColor('#FFFF00');
            
            $this->entityManager->persist($office);
            $this->entityManager->flush();

            // Create matching event
            $matchingEvent = new Event();
            $matchingEvent->setTitle('Matching Event ' . $timestamp);
            $matchingEvent->setStartTime(new \DateTime('today 10:00'));
            $matchingEvent->setEndTime(new \DateTime('today 11:00'));
            $matchingEvent->setColor('#FF0000');
            $matchingEvent->setCreator($user);
            $matchingEvent->setOffice($office);
            $matchingEvent->setPriority($priority);
            $matchingEvent->setStatus($status);
            $matchingEvent->setAllDay($isAllDay);

            // Create non-matching event (different priority)
            $nonMatchingEvent = new Event();
            $nonMatchingEvent->setTitle('Non-Matching Event ' . $timestamp);
            $nonMatchingEvent->setStartTime(new \DateTime('today 12:00'));
            $nonMatchingEvent->setEndTime(new \DateTime('today 13:00'));
            $nonMatchingEvent->setColor('#00FF00');
            $nonMatchingEvent->setCreator($user);
            $nonMatchingEvent->setOffice($office);
            $nonMatchingEvent->setPriority($priority === 'high' ? 'low' : 'high');
            $nonMatchingEvent->setStatus($status);
            $nonMatchingEvent->setAllDay($isAllDay);

            $this->entityManager->persist($matchingEvent);
            $this->entityManager->persist($nonMatchingEvent);
            $this->entityManager->flush();

            // Property: Combined criteria must return only matching events
            $combinedCriteria = [
                'office_ids' => [$office->getId()],
                'priority' => $priority,
                'status' => $status,
                'is_all_day' => $isAllDay
            ];
            
            $results = $this->eventRepository->searchEventsAdvanced($combinedCriteria);
            
            $this->assertContains($matchingEvent, $results, 
                'Combined criteria must return matching event');
            $this->assertNotContains($nonMatchingEvent, $results, 
                'Combined criteria must not return non-matching event');

            // Property: All returned events must match all criteria
            foreach ($results as $result) {
                $this->assertEquals($office->getId(), $result->getOffice()->getId(), 
                    'All results must match office criteria');
                $this->assertEquals($priority, $result->getPriority(), 
                    'All results must match priority criteria');
                $this->assertEquals($status, $result->getStatus(), 
                    'All results must match status criteria');
                $this->assertEquals($isAllDay, $result->isAllDay(), 
                    'All results must match all-day criteria');
            }

            // Clean up
            $this->entityManager->remove($matchingEvent);
            $this->entityManager->remove($nonMatchingEvent);
            $this->entityManager->remove($office);
            $this->entityManager->remove($user);
            $this->entityManager->flush();
        });
    }

    /**
     * Assert that events are in chronological order
     */
    private function assertChronologicalOrder(array $events, string $message = ''): void
    {
        for ($i = 1; $i < count($events); $i++) {
            $prevEvent = $events[$i - 1];
            $currentEvent = $events[$i];
            
            $this->assertLessThanOrEqual(
                $prevEvent->getStartTime(),
                $currentEvent->getStartTime(),
                $message ?: 'Events must be in chronological order'
            );
        }
    }
}