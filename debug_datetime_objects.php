<?php
// Debug the DateTime objects from the Event entity
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Entity\Event;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\DependencyInjection\ContainerBuilder;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Bootstrap Symfony to get the EntityManager
require_once 'config/bootstrap.php';

$kernel = new \App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine.orm.entity_manager');

echo "<h1>🔍 Debugging DateTime Objects from Event Entity</h1>\n";

// Get Event 76
$event = $entityManager->getRepository(Event::class)->find(76);

if (!$event) {
    echo "<p style='color: red;'>Event 76 not found!</p>\n";
    exit;
}

echo "<h2>Event 76 DateTime Analysis</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Property</th><th>Value</th><th>Type</th><th>Timezone</th><th>UTC Format</th><th>Philippines Format</th></tr>\n";

$startTime = $event->getStartTime();
$endTime = $event->getEndTime();

// Analyze startTime
if ($startTime) {
    $startTimeClass = get_class($startTime);
    $startTimeZone = $startTime->getTimezone()->getName();
    $startUtcFormat = $startTime->format('Y-m-d H:i:s T');
    
    // Convert to Philippines manually
    $startPhilippines = clone $startTime;
    $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    $startPhilippinesFormat = $startPhilippines->format('Y-m-d H:i:s T');
    
    echo "<tr>\n";
    echo "<td>startTime</td>\n";
    echo "<td>{$startTime->format('Y-m-d H:i:s')}</td>\n";
    echo "<td>$startTimeClass</td>\n";
    echo "<td><strong>$startTimeZone</strong></td>\n";
    echo "<td>$startUtcFormat</td>\n";
    echo "<td><strong style='color: green;'>$startPhilippinesFormat</strong></td>\n";
    echo "</tr>\n";
}

// Analyze endTime
if ($endTime) {
    $endTimeClass = get_class($endTime);
    $endTimeZone = $endTime->getTimezone()->getName();
    $endUtcFormat = $endTime->format('Y-m-d H:i:s T');
    
    // Convert to Philippines manually
    $endPhilippines = clone $endTime;
    $endPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    $endPhilippinesFormat = $endPhilippines->format('Y-m-d H:i:s T');
    
    echo "<tr>\n";
    echo "<td>endTime</td>\n";
    echo "<td>{$endTime->format('Y-m-d H:i:s')}</td>\n";
    echo "<td>$endTimeClass</td>\n";
    echo "<td><strong>$endTimeZone</strong></td>\n";
    echo "<td>$endUtcFormat</td>\n";
    echo "<td><strong style='color: green;'>$endPhilippinesFormat</strong></td>\n";
    echo "</tr>\n";
}

echo "</table>\n";

echo "<h2>🔍 Diagnosis</h2>\n";
echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";

if ($startTime && $startTime->getTimezone()->getName() !== 'UTC') {
    echo "<h3>❌ PROBLEM FOUND!</h3>\n";
    echo "<p>The DateTime objects from the database are NOT in UTC timezone!</p>\n";
    echo "<p>Current timezone: <strong>{$startTime->getTimezone()->getName()}</strong></p>\n";
    echo "<p>Expected timezone: <strong>UTC</strong></p>\n";
    echo "<h4>This explains why the Twig filters aren't working:</h4>\n";
    echo "<ul>\n";
    echo "<li>The filters expect UTC input but are getting {$startTime->getTimezone()->getName()} input</li>\n";
    echo "<li>When they try to convert from UTC to Philippines, they're actually converting from {$startTime->getTimezone()->getName()} to Philippines</li>\n";
    echo "<li>This results in incorrect time display</li>\n";
    echo "</ul>\n";
} else {
    echo "<h3>✅ DateTime Timezone is Correct</h3>\n";
    echo "<p>The DateTime objects are properly in UTC timezone.</p>\n";
    echo "<p>The issue must be elsewhere in the Twig filter implementation.</p>\n";
}

echo "</div>\n";

// Test the TimezoneService directly with these DateTime objects
echo "<h2>Testing TimezoneService with Event DateTime Objects</h2>\n";

try {
    $timezoneService = $container->get('App\Service\TimezoneService');
    
    if ($startTime) {
        $convertedStart = $timezoneService->convertFromUtc($startTime);
        $frontendFormat = $timezoneService->formatForFrontend($startTime);
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Method</th><th>Input</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
        
        $convertedResult = $convertedStart->format('Y-m-d H:i:s T');
        $expectedConverted = '2026-02-12 08:00:00 PST';
        $convertedStatus = (strpos($convertedResult, '08:00:00') !== false) ? '✅ PASS' : '❌ FAIL';
        
        echo "<tr><td>convertFromUtc</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$convertedResult</strong></td><td>$expectedConverted</td><td>$convertedStatus</td></tr>\n";
        
        $expectedFrontend = '2026-02-12T08:00';
        $frontendStatus = ($frontendFormat === $expectedFrontend) ? '✅ PASS' : '❌ FAIL';
        
        echo "<tr><td>formatForFrontend</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$frontendFormat</strong></td><td>$expectedFrontend</td><td>$frontendStatus</td></tr>\n";
        
        echo "</table>\n";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error testing TimezoneService: {$e->getMessage()}</p>\n";
}

echo "<h2>🧪 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Check the results above to see if DateTime objects have correct timezone</li>\n";
echo "<li>Test the filter test page: <a href='http://127.0.0.4:8000/test-filters' target='_blank'>http://127.0.0.4:8000/test-filters</a></li>\n";
echo "<li>If DateTime timezone is wrong, we need to configure Doctrine properly</li>\n";
echo "<li>If DateTime timezone is correct, the issue is in the Twig filter implementation</li>\n";
echo "</ol>\n";
?>