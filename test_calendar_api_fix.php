<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== TESTING CALENDAR API FIX ===\n\n";

// Get Event 76
$eventRepository = $container->get('doctrine')->getRepository(\App\Entity\Event::class);
$event = $eventRepository->find(76);

if ($event) {
    echo "Event 76 Database Time (UTC): " . $event->getStartTime()->format('Y-m-d H:i:s') . "\n\n";
    
    echo "=== WHAT CALENDAR API NOW SENDS ===\n";
    // This is what the updated CalendarController will send
    $startUtc = new DateTime($event->getStartTime()->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    $endUtc = new DateTime($event->getEndTime()->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    
    $apiData = [
        'start' => $startUtc->format('Y-m-d\TH:i:s\Z'), // Pure UTC format
        'end' => $endUtc->format('Y-m-d\TH:i:s\Z'),
    ];
    
    echo "API sends 'start': " . $apiData['start'] . "\n";
    echo "API sends 'end': " . $apiData['end'] . "\n\n";
    
    echo "=== WHAT FULLCALENDAR WILL DO ===\n";
    echo "1. FullCalendar receives: " . $apiData['start'] . "\n";
    echo "2. FullCalendar has timeZone: 'Asia/Manila' configured\n";
    echo "3. FullCalendar converts UTC to Manila time\n";
    echo "4. Result should be: 8:00 AM\n\n";
    
    // Simulate what FullCalendar does
    $utcDate = new DateTime($apiData['start']);
    echo "UTC Date object: " . $utcDate->format('Y-m-d H:i:s P') . "\n";
    
    $manilaDate = clone $utcDate;
    $manilaDate->setTimezone(new DateTimeZone('Asia/Manila'));
    echo "Manila Date object: " . $manilaDate->format('Y-m-d H:i:s P') . "\n";
    echo "Manila time display: " . $manilaDate->format('g:i A') . "\n\n";
    
    echo "=== COMPARISON WITH EVENT PAGES ===\n";
    $timezoneService = $container->get(\App\Service\TimezoneService::class);
    $eventPageTime = $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A');
    echo "Event pages show: $eventPageTime\n";
    echo "Calendar should show: " . $manilaDate->format('g:i A') . "\n";
    
    if ($eventPageTime === $manilaDate->format('g:i A')) {
        echo "✅ BOTH SYSTEMS WILL BE SYNCHRONIZED!\n";
    } else {
        echo "❌ SYSTEMS STILL NOT SYNCHRONIZED!\n";
    }
    
} else {
    echo "❌ Event 76 not found!\n";
}