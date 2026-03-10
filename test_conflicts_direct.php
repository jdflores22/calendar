<?php

require_once 'vendor/autoload.php';

use App\Kernel;
use App\Entity\Event;
use App\Entity\User;
use App\Service\ConflictResolverService;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

// Boot Symfony kernel
$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$entityManager = $container->get('doctrine.orm.entity_manager');
$conflictResolver = $container->get(ConflictResolverService::class);

// Get repositories
$userRepository = $entityManager->getRepository(User::class);
$eventRepository = $entityManager->getRepository(Event::class);

echo "=== TESDA Calendar Conflict Detection Test ===\n\n";

// Test 1: Check conflicts for February 2, 2026 10:00-11:00
echo "Test 1: Checking conflicts for Feb 2, 2026 10:00-11:00\n";
echo "Expected: 3 conflicts (Feb 2 Meeting A, B, and Workshop)\n";

$testStart = new \DateTime('2026-02-02 10:00:00');
$testEnd = new \DateTime('2026-02-02 11:00:00');

$conflicts = $conflictResolver->checkConflicts($testStart, $testEnd);
echo "Found conflicts: " . count($conflicts) . "\n";

foreach ($conflicts as $conflict) {
    echo "  - {$conflict->getTitle()} ({$conflict->getStartTime()->format('H:i')} - {$conflict->getEndTime()->format('H:i')})\n";
}

echo "\n";

// Test 2: Check conflicts for February 3, 2026 09:00-11:00
echo "Test 2: Checking conflicts for Feb 3, 2026 09:00-11:00\n";
echo "Expected: 5 conflicts (Monthly Directors Meeting duplicates + 3 new conflicts)\n";

$testStart2 = new \DateTime('2026-02-03 09:00:00');
$testEnd2 = new \DateTime('2026-02-03 11:00:00');

$conflicts2 = $conflictResolver->checkConflicts($testStart2, $testEnd2);
echo "Found conflicts: " . count($conflicts2) . "\n";

foreach ($conflicts2 as $conflict) {
    echo "  - {$conflict->getTitle()} ({$conflict->getStartTime()->format('H:i')} - {$conflict->getEndTime()->format('H:i')})\n";
}

echo "\n";

// Test 3: Check conflicts for a free time slot
echo "Test 3: Checking conflicts for Feb 2, 2026 14:00-15:00 (should be free)\n";

$testStart3 = new \DateTime('2026-02-02 14:00:00');
$testEnd3 = new \DateTime('2026-02-02 15:00:00');

$conflicts3 = $conflictResolver->checkConflicts($testStart3, $testEnd3);
echo "Found conflicts: " . count($conflicts3) . "\n";

if (count($conflicts3) === 0) {
    echo "  ✓ No conflicts found - time slot is available\n";
}

echo "\n";

// Test 4: Test the formatEventForCalendar method by checking an existing event
echo "Test 4: Testing event formatting with conflict detection\n";

$existingEvent = $eventRepository->findOneBy(['title' => 'Feb 2 Meeting A']);
if ($existingEvent) {
    // Get the CalendarController to test the formatEventForCalendar method
    $calendarController = $container->get('App\Controller\CalendarController');
    
    // Use reflection to access the private method
    $reflection = new \ReflectionClass($calendarController);
    $method = $reflection->getMethod('formatEventForCalendar');
    $method->setAccessible(true);
    
    $formattedEvent = $method->invoke($calendarController, $existingEvent);
    
    echo "Event: {$formattedEvent['title']}\n";
    echo "Has conflicts: " . ($formattedEvent['extendedProps']['hasConflicts'] ? 'Yes' : 'No') . "\n";
    echo "Conflict count: {$formattedEvent['extendedProps']['conflictCount']}\n";
    
    if ($formattedEvent['extendedProps']['hasConflicts']) {
        echo "Conflicting events:\n";
        foreach ($formattedEvent['extendedProps']['conflicts'] as $conflict) {
            echo "  - {$conflict['title']}\n";
        }
    }
} else {
    echo "Could not find test event\n";
}

echo "\n=== Test Complete ===\n";
echo "If conflicts are detected correctly, the calendar should show:\n";
echo "- Red badges for events with conflicts\n";
echo "- Exclamation icons on conflicting events\n";
echo "- Conflict count badges\n";
echo "- Detailed conflict information in event details\n";