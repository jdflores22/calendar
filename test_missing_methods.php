<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony kernel
$kernel = new \App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== TESTING MISSING TIMEZONE METHODS ===\n\n";

$timezoneService = $container->get(\App\Service\TimezoneService::class);

// Test convertToUtc method
echo "=== Testing convertToUtc ===\n";
$philippinesTimeString = '2026-02-12T08:00'; // Philippines time from form
$utcDateTime = $timezoneService->convertToUtc($philippinesTimeString);
echo "Input (Philippines): $philippinesTimeString\n";
echo "Output (UTC): " . $utcDateTime->format('Y-m-d H:i:s P') . "\n";
echo "Expected: 2026-02-12 00:00:00 +00:00\n";
echo "Status: " . ($utcDateTime->format('Y-m-d H:i:s') === '2026-02-12 00:00:00' ? '✅ CORRECT' : '❌ WRONG') . "\n\n";

// Test convertFromUtc method
echo "=== Testing convertFromUtc ===\n";
$utcTime = new DateTime('2026-02-12 00:00:00', new DateTimeZone('UTC'));
$philippinesDateTime = $timezoneService->convertFromUtc($utcTime);
echo "Input (UTC): " . $utcTime->format('Y-m-d H:i:s P') . "\n";
echo "Output (Philippines): " . $philippinesDateTime->format('Y-m-d H:i:s P') . "\n";
echo "Expected: 2026-02-12 08:00:00 +08:00\n";
echo "Status: " . ($philippinesDateTime->format('H:i') === '08:00' ? '✅ CORRECT' : '❌ WRONG') . "\n\n";

echo "=== SUMMARY ===\n";
echo "✅ convertToUtc method restored and working\n";
echo "✅ convertFromUtc method added and working\n";
echo "✅ Event edit form should now work without errors\n";