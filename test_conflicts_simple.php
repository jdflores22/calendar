<?php

require_once 'vendor/autoload.php';

use App\Kernel;
use App\Entity\Event;
use App\Repository\EventRepository;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

// Boot Symfony kernel
$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get entity manager
$entityManager = $container->get('doctrine.orm.entity_manager');
$eventRepository = $entityManager->getRepository(Event::class);

echo "=== TESDA Calendar Conflict Detection Test ===\n\n";

// Test 1: Check conflicts using repository method
echo "Test 1: Checking conflicts for Feb 2, 2026 10:00-11:00\n";

$testStart = new \DateTime('2026-02-02 10:00:00');
$testEnd = new \DateTime('2026-02-02 11:00:00');

$conflicts = $eventRepository->findConflictingEvents($testStart, $testEnd);
echo "Found conflicts: " . count($conflicts) . "\n";

foreach ($conflicts as $conflict) {
    echo "  - {$conflict->getTitle()} ({$conflict->getStartTime()->format('H:i')} - {$conflict->getEndTime()->format('H:i')})\n";
}

echo "\n";

// Test 2: Check conflicts for February 3, 2026 09:00-11:00
echo "Test 2: Checking conflicts for Feb 3, 2026 09:00-11:00\n";

$testStart2 = new \DateTime('2026-02-03 09:00:00');
$testEnd2 = new \DateTime('2026-02-03 11:00:00');

$conflicts2 = $eventRepository->findConflictingEvents($testStart2, $testEnd2);
echo "Found conflicts: " . count($conflicts2) . "\n";

foreach ($conflicts2 as $conflict) {
    echo "  - {$conflict->getTitle()} ({$conflict->getStartTime()->format('H:i')} - {$conflict->getEndTime()->format('H:i')})\n";
}

echo "\n";

// Test 3: Show all events for February 2026 with their times
echo "Test 3: All events in February 2026:\n";

$februaryEvents = $eventRepository->createQueryBuilder('e')
    ->where('e.startTime >= :start')
    ->andWhere('e.startTime < :end')
    ->setParameter('start', new \DateTime('2026-02-01'))
    ->setParameter('end', new \DateTime('2026-03-01'))
    ->orderBy('e.startTime', 'ASC')
    ->getQuery()
    ->getResult();

$eventsByTime = [];
foreach ($februaryEvents as $event) {
    $timeKey = $event->getStartTime()->format('Y-m-d H:i') . ' - ' . $event->getEndTime()->format('H:i');
    if (!isset($eventsByTime[$timeKey])) {
        $eventsByTime[$timeKey] = [];
    }
    $eventsByTime[$timeKey][] = $event;
}

foreach ($eventsByTime as $timeSlot => $events) {
    $conflictCount = count($events);
    $conflictIndicator = $conflictCount > 1 ? " ⚠️  ({$conflictCount} CONFLICTS)" : "";
    echo "{$timeSlot}{$conflictIndicator}\n";
    
    foreach ($events as $event) {
        echo "  - {$event->getTitle()}\n";
    }
    echo "\n";
}

echo "=== Summary ===\n";
echo "Total events: " . count($februaryEvents) . "\n";

$conflictSlots = array_filter($eventsByTime, function($events) {
    return count($events) > 1;
});

echo "Time slots with conflicts: " . count($conflictSlots) . "\n";

$totalConflictingEvents = 0;
foreach ($conflictSlots as $events) {
    $totalConflictingEvents += count($events);
}

echo "Total conflicting events: {$totalConflictingEvents}\n";

echo "\n=== Calendar Test Instructions ===\n";
echo "1. Login to http://127.0.0.1:8000/login with admin@tesda.gov.ph / admin123\n";
echo "2. Go to http://127.0.0.1:8000/calendar\n";
echo "3. Look for events on February 2nd and 3rd\n";
echo "4. Events with conflicts should show:\n";
echo "   - Red badges instead of blue/green\n";
echo "   - Exclamation warning icons\n";
echo "   - Conflict count badges\n";
echo "5. Click on conflicting events to see conflict details\n";