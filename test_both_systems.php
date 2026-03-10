<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== TESTING BOTH TIMEZONE SYSTEMS ===\n\n";

// Get Event 76
$eventRepository = $container->get('doctrine')->getRepository(\App\Entity\Event::class);
$event = $eventRepository->find(76);

if ($event) {
    echo "Event 76 Database Time (UTC): " . $event->getStartTime()->format('Y-m-d H:i:s') . "\n\n";
    
    // Test PHP/Twig System (Server-side)
    $timezoneService = $container->get(\App\Service\TimezoneService::class);
    
    echo "=== PHP/TWIG SYSTEM (Event Pages) ===\n";
    echo "- ph_time filter result: " . $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A') . "\n";
    echo "- ph_datetime_local filter result: " . $timezoneService->toDateTimeLocal($event->getStartTime()) . "\n";
    echo "- toCalendarFormat result: " . $timezoneService->toCalendarFormat($event->getStartTime()) . "\n\n";
    
    // Simulate JavaScript System (Client-side)
    echo "=== JAVASCRIPT SYSTEM (Calendar) ===\n";
    echo "What JavaScript sees from API:\n";
    
    // This is what the CalendarController sends to the frontend
    $calendarData = [
        'id' => $event->getId(),
        'title' => $event->getTitle(),
        'start' => $timezoneService->toCalendarFormat($event->getStartTime()),
        'end' => $timezoneService->toCalendarFormat($event->getEndTime()),
    ];
    
    echo "- API sends 'start': " . $calendarData['start'] . "\n";
    
    // Simulate what JavaScript does with this data
    $startDateTime = new DateTime($calendarData['start']);
    echo "- JavaScript creates Date object: " . $startDateTime->format('Y-m-d H:i:s P') . "\n";
    echo "- JavaScript toLocaleTimeString with Asia/Manila: ";
    
    // Simulate the JavaScript formatting
    $jsFormatted = $startDateTime->format('g:i A');
    echo $jsFormatted . "\n\n";
    
    echo "=== COMPARISON ===\n";
    $phpResult = $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A');
    echo "PHP System shows: $phpResult\n";
    echo "JavaScript System shows: $jsFormatted\n";
    
    if ($phpResult === $jsFormatted) {
        echo "✅ BOTH SYSTEMS ARE SYNCHRONIZED!\n";
    } else {
        echo "❌ SYSTEMS ARE NOT SYNCHRONIZED!\n";
        echo "This means we have different timezone handling!\n";
    }
    
} else {
    echo "❌ Event 76 not found!\n";
}

echo "\n=== THE REAL QUESTION ===\n";
echo "Are we using the SAME timezone conversion logic everywhere?\n";
echo "Or do we have different systems that might show different times?\n";