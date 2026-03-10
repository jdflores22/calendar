<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== Testing Simplified Philippines Timezone System ===\n\n";

// Get Event 76
$eventRepository = $container->get('doctrine')->getRepository(\App\Entity\Event::class);
$event = $eventRepository->find(76);

if ($event) {
    echo "Event 76 Details:\n";
    echo "- Title: " . $event->getTitle() . "\n";
    echo "- Database Start Time (UTC): " . $event->getStartTime()->format('Y-m-d H:i:s') . "\n";
    echo "- Database End Time (UTC): " . $event->getEndTime()->format('Y-m-d H:i:s') . "\n\n";
    
    // Test the new TimezoneService directly
    $timezoneService = $container->get(\App\Service\TimezoneService::class);
    
    echo "=== TimezoneService Results ===\n";
    echo "- toDisplayTime (g:i A): " . $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A') . "\n";
    echo "- toDateTimeLocal: " . $timezoneService->toDateTimeLocal($event->getStartTime()) . "\n";
    echo "- toCalendarFormat: " . $timezoneService->toCalendarFormat($event->getStartTime()) . "\n\n";
    
    // Expected results
    echo "=== Expected Results ===\n";
    echo "- Time Display: 8:00 AM\n";
    echo "- DateTime Local: 2026-02-12T08:00\n";
    echo "- Calendar Format: 2026-02-12T08:00:00+08:00\n\n";
    
    // Status check
    $timeResult = $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A');
    $datetimeResult = $timezoneService->toDateTimeLocal($event->getStartTime());
    $calendarResult = $timezoneService->toCalendarFormat($event->getStartTime());
    
    echo "=== Status Check ===\n";
    echo "Time Display: " . ($timeResult === '8:00 AM' ? '✅ CORRECT' : '❌ WRONG') . " ($timeResult)\n";
    echo "DateTime Local: " . ($datetimeResult === '2026-02-12T08:00' ? '✅ CORRECT' : '❌ WRONG') . " ($datetimeResult)\n";
    echo "Calendar Format: " . (strpos($calendarResult, '08:00:00+08:00') !== false ? '✅ CORRECT' : '❌ WRONG') . " ($calendarResult)\n\n";
    
    echo "=== SUMMARY ===\n";
    if ($timeResult === '8:00 AM' && $datetimeResult === '2026-02-12T08:00') {
        echo "✅ SUCCESS! TimezoneService is working correctly!\n";
        echo "✅ Event 76 will now display as 8:00 AM (Philippines time) everywhere!\n";
        echo "✅ Calendar, event details, and edit forms will all be synchronized!\n\n";
        echo "🎯 The simplified timezone system is ready!\n";
    } else {
        echo "❌ There are still issues with the timezone conversion.\n";
    }
    
} else {
    echo "❌ Event 76 not found!\n";
}