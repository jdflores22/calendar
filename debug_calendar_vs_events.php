<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== DEBUGGING CALENDAR vs EVENTS TIMEZONE MISMATCH ===\n\n";

// Get Event 76
$eventRepository = $container->get('doctrine')->getRepository(\App\Entity\Event::class);
$event = $eventRepository->find(76);

if ($event) {
    echo "Event 76 Database Time (UTC): " . $event->getStartTime()->format('Y-m-d H:i:s') . "\n\n";
    
    // Test what the CalendarController actually sends
    $timezoneService = $container->get(\App\Service\TimezoneService::class);
    
    echo "=== WHAT CALENDAR API SENDS ===\n";
    $calendarFormat = $timezoneService->toCalendarFormat($event->getStartTime());
    echo "toCalendarFormat result: $calendarFormat\n";
    
    // Parse what JavaScript would receive
    $jsDate = new DateTime($calendarFormat);
    echo "JavaScript Date object: " . $jsDate->format('Y-m-d H:i:s P') . "\n";
    echo "JavaScript Date in UTC: " . $jsDate->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') . "\n";
    
    // What JavaScript formatting would show
    $jsDate->setTimezone(new DateTimeZone('Asia/Manila'));
    echo "JavaScript with Asia/Manila: " . $jsDate->format('g:i A') . "\n\n";
    
    echo "=== WHAT EVENT PAGES SHOW ===\n";
    echo "ph_time filter result: " . $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A') . "\n";
    echo "ph_datetime_local result: " . $timezoneService->toDateTimeLocal($event->getStartTime()) . "\n\n";
    
    echo "=== ANALYSIS ===\n";
    echo "If calendar shows 4:00 PM and events show 8:00 AM, then:\n";
    echo "- Calendar is adding 8 hours TWICE (UTC+0 → UTC+8 → UTC+16)\n";
    echo "- Events are adding 8 hours ONCE (UTC+0 → UTC+8)\n\n";
    
    echo "=== TESTING THEORY ===\n";
    // Test if the calendar is double-converting
    $utcTime = $event->getStartTime(); // 2026-02-12 00:00:00 UTC
    echo "Original UTC: " . $utcTime->format('Y-m-d H:i:s') . "\n";
    
    // First conversion (what our TimezoneService does)
    $firstConversion = $timezoneService->toCalendarFormat($utcTime);
    echo "First conversion: $firstConversion\n";
    
    // What if JavaScript treats this as UTC and converts again?
    $doubleConverted = new DateTime($firstConversion);
    $doubleConverted->setTimezone(new DateTimeZone('UTC')); // Treat as UTC
    $doubleConverted->setTimezone(new DateTimeZone('Asia/Manila')); // Convert to Manila
    echo "Double conversion result: " . $doubleConverted->format('g:i A') . "\n";
    
    if ($doubleConverted->format('g:i A') === '4:00 PM') {
        echo "🎯 FOUND THE PROBLEM: Calendar is double-converting timezone!\n";
    }
    
} else {
    echo "❌ Event 76 not found!\n";
}