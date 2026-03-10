<?php
// Test the timezone fix within Symfony context
use Symfony\Component\HttpFoundation\Request;
use App\Kernel;

require_once 'vendor/autoload.php';

// Load environment
$dotenv = new Symfony\Component\Dotenv\Dotenv();
$dotenv->load('.env.local');
$dotenv->load('.env');

// Set default values if not loaded
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'dev';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '1';

// Create kernel and boot it
$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();

echo "<h1>🧪 Symfony Timezone Fix Test</h1>\n";

// Test 1: Check PHP timezone after Symfony bootstrap
echo "<h2>Test 1: PHP Timezone Configuration</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>\n";

$phpTimezone = date_default_timezone_get();
$phpStatus = ($phpTimezone === 'UTC') ? '✅ CORRECT' : '❌ WRONG';
echo "<tr><td>PHP Default Timezone</td><td><strong>$phpTimezone</strong></td><td>$phpStatus</td></tr>\n";

$currentUtc = gmdate('Y-m-d H:i:s');
echo "<tr><td>Current UTC Time</td><td>$currentUtc</td><td>ℹ️ Reference</td></tr>\n";

$currentPhilippines = (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s T');
echo "<tr><td>Current Philippines Time</td><td>$currentPhilippines</td><td>ℹ️ Reference</td></tr>\n";

echo "</table>\n";

// Test 2: Get Event 76 through Doctrine
echo "<h2>Test 2: Event 76 via Doctrine</h2>\n";

try {
    $entityManager = $container->get('doctrine.orm.entity_manager');
    $event = $entityManager->getRepository('App\Entity\Event')->find(76);
    
    if (!$event) {
        echo "<p style='color: red;'>❌ Event 76 not found</p>\n";
    } else {
        $startTime = $event->getStartTime();
        $endTime = $event->getEndTime();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Property</th><th>Raw Value</th><th>Timezone</th><th>UTC Format</th><th>Philippines Format</th><th>Status</th></tr>\n";
        
        // Analyze start time
        $startTz = $startTime->getTimezone()->getName();
        $startUtc = clone $startTime;
        $startUtc->setTimezone(new DateTimeZone('UTC'));
        $startPhilippines = clone $startTime;
        $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
        
        $startStatus = ($startTz === 'UTC') ? '✅ CORRECT' : '❌ WRONG';
        
        echo "<tr>\n";
        echo "<td>startTime</td>\n";
        echo "<td>{$startTime->format('Y-m-d H:i:s')}</td>\n";
        echo "<td><strong>$startTz</strong></td>\n";
        echo "<td>{$startUtc->format('Y-m-d H:i:s T')}</td>\n";
        echo "<td><strong style='color: green;'>{$startPhilippines->format('Y-m-d H:i:s T')}</strong></td>\n";
        echo "<td>$startStatus</td>\n";
        echo "</tr>\n";
        
        echo "</table>\n";
        
        // Test 3: Test TimezoneService
        echo "<h2>Test 3: TimezoneService with Event DateTime</h2>\n";
        
        $timezoneService = $container->get('App\Service\TimezoneService');
        
        $convertedStart = $timezoneService->convertFromUtc($startTime);
        $frontendFormat = $timezoneService->formatForFrontend($startTime);
        $displayFormat = $timezoneService->formatForDisplay($startTime, 'l, F j, Y g:i A');
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
        echo "<tr><th>Method</th><th>Input</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
        
        $convertedResult = $convertedStart->format('Y-m-d H:i:s T');
        $expectedConverted = '2026-02-12 08:00:00 PST';
        $convertedStatus = (strpos($convertedResult, '08:00:00') !== false) ? '✅ PASS' : '❌ FAIL';
        
        echo "<tr><td>convertFromUtc</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$convertedResult</strong></td><td>$expectedConverted</td><td>$convertedStatus</td></tr>\n";
        
        $expectedFrontend = '2026-02-12T08:00';
        $frontendStatus = ($frontendFormat === $expectedFrontend) ? '✅ PASS' : '❌ FAIL';
        
        echo "<tr><td>formatForFrontend</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$frontendFormat</strong></td><td>$expectedFrontend</td><td>$frontendStatus</td></tr>\n";
        
        $expectedDisplay = 'Wednesday, February 12, 2026 8:00 AM';
        $displayStatus = (strpos($displayFormat, '8:00 AM') !== false) ? '✅ PASS' : '❌ FAIL';
        
        echo "<tr><td>formatForDisplay</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$displayFormat</strong></td><td>$expectedDisplay</td><td>$displayStatus</td></tr>\n";
        
        echo "</table>\n";
        
        // Test 4: Test Twig Filters
        echo "<h2>Test 4: Twig Filters</h2>\n";
        
        $twig = $container->get('twig');
        
        try {
            // Create a simple template to test the filters
            $template = $twig->createTemplate('{{ startTime|system_time }} | {{ startTime|system_date("l, F j, Y g:i A") }}');
            $result = $template->render(['startTime' => $startTime]);
            
            echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
            echo "<tr><th>Filter Test</th><th>Input</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
            
            $parts = explode(' | ', $result);
            $systemTimeResult = trim($parts[0] ?? '');
            $systemDateResult = trim($parts[1] ?? '');
            
            $systemTimeStatus = ($systemTimeResult === '2026-02-12T08:00') ? '✅ PASS' : '❌ FAIL';
            $systemDateStatus = (strpos($systemDateResult, '8:00 AM') !== false) ? '✅ PASS' : '❌ FAIL';
            
            echo "<tr><td>system_time filter</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$systemTimeResult</strong></td><td>2026-02-12T08:00</td><td>$systemTimeStatus</td></tr>\n";
            echo "<tr><td>system_date filter</td><td>{$startTime->format('Y-m-d H:i:s T')}</td><td><strong>$systemDateResult</strong></td><td>Wednesday, February 12, 2026 8:00 AM</td><td>$systemDateStatus</td></tr>\n";
            
            echo "</table>\n";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ Twig filter test failed: {$e->getMessage()}</p>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: {$e->getMessage()}</p>\n";
}

echo "<h2>🎯 Summary</h2>\n";
echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<p>If all tests show ✅ PASS, then the timezone fix is working correctly and the event pages should now display Philippines time properly.</p>\n";
echo "<p>If any tests show ❌ FAIL, there are still issues to resolve.</p>\n";
echo "</div>\n";

echo "<h2>🔗 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Check the results above</li>\n";
echo "<li>If all tests pass, visit the event pages:</li>\n";
echo "<ul>\n";
echo "<li><a href='http://127.0.0.4:8000/events/76' target='_blank'>Event 76 Details</a></li>\n";
echo "<li><a href='http://127.0.0.4:8000/events/76/edit' target='_blank'>Event 76 Edit</a></li>\n";
echo "</ul>\n";
echo "<li>Verify that times now show as 8:00 AM instead of 12:00 AM</li>\n";
echo "</ol>\n";

$kernel->shutdown();
?>