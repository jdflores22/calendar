<?php
require_once 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
use App\Service\TimezoneService;
use App\Twig\TimezoneExtension;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Test the TimezoneService and Twig extension
$timezoneService = new TimezoneService();
$twigExtension = new TimezoneExtension($timezoneService);

// Create a test datetime in UTC (like what comes from database)
$utcDateTime = new DateTime('2026-02-19 18:00:00', new DateTimeZone('UTC'));

echo "=== Twig Filter Test ===\n";
echo "UTC DateTime: " . $utcDateTime->format('Y-m-d H:i:s T') . "\n";

// Test the TimezoneService directly
echo "\n=== TimezoneService Methods ===\n";
echo "formatForFrontend(): " . $timezoneService->formatForFrontend($utcDateTime) . "\n";
echo "formatForDisplay(): " . $timezoneService->formatForDisplay($utcDateTime) . "\n";

// Test the Twig extension
echo "\n=== Twig Extension Methods ===\n";
echo "philippines_time filter: " . $twigExtension->convertToPhilippinesTime($utcDateTime) . "\n";
echo "philippines_date filter: " . $twigExtension->formatPhilippinesDate($utcDateTime) . "\n";

// Test conversion
$philippinesTime = $timezoneService->convertFromUtc($utcDateTime);
echo "\n=== Conversion Check ===\n";
echo "Philippines DateTime: " . $philippinesTime->format('Y-m-d H:i:s T') . "\n";
echo "Expected in form: 2026-02-20T02:00\n";
echo "Actual from filter: " . $twigExtension->convertToPhilippinesTime($utcDateTime) . "\n";

if ($twigExtension->convertToPhilippinesTime($utcDateTime) === '2026-02-20T02:00') {
    echo "✅ Twig filter working correctly!\n";
} else {
    echo "❌ Twig filter not working as expected\n";
}
?>