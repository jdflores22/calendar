<?php

// Simple test to verify conflict detection works
require_once 'vendor/autoload.php';

use App\Entity\Event;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;

// Bootstrap Symfony
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get('doctrine.orm.entity_manager');

/** @var EventRepository $eventRepository */
$eventRepository = $entityManager->getRepository(Event::class);

echo "Testing Conflict Detection\n";
echo "==========================\n\n";

// Test 1: Check for conflicts with existing events
echo "Test 1: Checking for conflicts in a busy time slot\n";
$testStart = new \DateTime('2026-02-03 09:30:00'); // Should conflict with morning meeting
$testEnd = new \DateTime('2026-02-03 10:30:00');

$conflicts = $eventRepository->findConflictingEvents($testStart, $testEnd);

echo "Time slot: {$testStart->format('Y-m-d H:i')} - {$testEnd->format('Y-m-d H:i')}\n";
echo "Found conflicts: " . count($conflicts) . "\n";

if (count($conflicts) > 0) {
    echo "Conflicting events:\n";
    foreach ($conflicts as $conflict) {
        echo "  - {$conflict->getTitle()} ({$conflict->getStartTime()->format('Y-m-d H:i')} - {$conflict->getEndTime()->format('Y-m-d H:i')})\n";
    }
} else {
    echo "No conflicts found.\n";
}

echo "\n";

// Test 2: Check for conflicts in a free time slot
echo "Test 2: Checking for conflicts in a free time slot\n";
$testStart2 = new \DateTime('2026-02-03 11:00:00'); // Should be free
$testEnd2 = new \DateTime('2026-02-03 12:00:00');

$conflicts2 = $eventRepository->findConflictingEvents($testStart2, $testEnd2);

echo "Time slot: {$testStart2->format('Y-m-d H:i')} - {$testEnd2->format('Y-m-d H:i')}\n";
echo "Found conflicts: " . count($conflicts2) . "\n";

if (count($conflicts2) > 0) {
    echo "Conflicting events:\n";
    foreach ($conflicts2 as $conflict) {
        echo "  - {$conflict->getTitle()} ({$conflict->getStartTime()->format('Y-m-d H:i')} - {$conflict->getEndTime()->format('Y-m-d H:i')})\n";
    }
} else {
    echo "No conflicts found.\n";
}

echo "\n";

// Test 3: List all events to see what we have
echo "Test 3: All events in the system\n";
$allEvents = $eventRepository->findAll();
echo "Total events: " . count($allEvents) . "\n";

if (count($allEvents) > 0) {
    echo "Events:\n";
    foreach ($allEvents as $event) {
        echo "  - {$event->getTitle()} ({$event->getStartTime()->format('Y-m-d H:i')} - {$event->getEndTime()->format('Y-m-d H:i')})\n";
    }
}

echo "\nConflict detection test completed!\n";