<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== Testing New Philippines Timezone Filters ===\n\n";

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
    
    // Test Twig filters
    $twig = $container->get('twig');
    $template = $twig->createTemplate('
Start Time: {{ startTime|ph_time("g:i A") }}
Date Time Local: {{ startTime|ph_datetime_local }}
Full Date: {{ startTime|ph_date("l, F j, Y g:i A") }}
');
    
    echo "=== Twig Filter Results ===\n";
    echo $template->render(['startTime' => $event->getStartTime()]);
    echo "\n";
    
    // Expected results
    echo "=== Expected Results ===\n";
    echo "- Time Display: 8:00 AM\n";
    echo "- DateTime Local: 2026-02-12T08:00\n";
    echo "- Full Date: Wednesday, February 12, 2026 8:00 AM\n\n";
    
    // Status check
    $timeResult = $timezoneService->toDisplayTime($event->getStartTime(), 'g:i A');
    $datetimeResult = $timezoneService->toDateTimeLocal($event->getStartTime());
    
    echo "=== Status Check ===\n";
    echo "Time Display: " . ($timeResult === '8:00 AM' ? '✅ CORRECT' : '❌ WRONG') . " ($timeResult)\n";
    echo "DateTime Local: " . ($datetimeResult === '2026-02-12T08:00' ? '✅ CORRECT' : '❌ WRONG') . " ($datetimeResult)\n";
    
} else {
    echo "❌ Event 76 not found!\n";
}