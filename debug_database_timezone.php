<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== DEBUGGING DATABASE TIMEZONE ===\n\n";

// Get Event 76
$eventRepository = $container->get('doctrine')->getRepository(\App\Entity\Event::class);
$event = $eventRepository->find(76);

if ($event) {
    $startTime = $event->getStartTime();
    
    echo "Event 76 Start Time Analysis:\n";
    echo "- Raw format: " . $startTime->format('Y-m-d H:i:s') . "\n";
    echo "- With timezone: " . $startTime->format('Y-m-d H:i:s P') . "\n";
    echo "- Timezone name: " . $startTime->getTimezone()->getName() . "\n";
    echo "- UTC format: " . $startTime->format('c') . "\n\n";
    
    // Test creating a proper UTC DateTime
    echo "Creating proper UTC DateTime:\n";
    $utcTime = new DateTime($startTime->format('Y-m-d H:i:s'), new DateTimeZone('UTC'));
    echo "- UTC DateTime: " . $utcTime->format('Y-m-d H:i:s P') . "\n";
    echo "- UTC format: " . $utcTime->format('c') . "\n";
    echo "- Z format: " . $utcTime->format('Y-m-d\TH:i:s\Z') . "\n\n";
    
    // Convert to Manila
    $manilaTime = clone $utcTime;
    $manilaTime->setTimezone(new DateTimeZone('Asia/Manila'));
    echo "Manila conversion:\n";
    echo "- Manila DateTime: " . $manilaTime->format('Y-m-d H:i:s P') . "\n";
    echo "- Manila display: " . $manilaTime->format('g:i A') . "\n\n";
    
    // Test what TimezoneService does
    $timezoneService = $container->get(\App\Service\TimezoneService::class);
    echo "TimezoneService result: " . $timezoneService->toDisplayTime($startTime, 'g:i A') . "\n\n";
    
    echo "=== CONCLUSION ===\n";
    if ($manilaTime->format('g:i A') === '8:00 AM') {
        echo "✅ Proper UTC handling gives 8:00 AM\n";
        echo "✅ This should fix the calendar display\n";
    } else {
        echo "❌ Still not getting 8:00 AM\n";
    }
    
} else {
    echo "❌ Event 76 not found!\n";
}