<?php
// Test the TimezoneService fix
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');
$dotenv->load('.env');

// Set default values if not loaded
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'dev';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '1';

// Create kernel and boot it
$kernel = new \App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();

echo "<h1>🔧 TimezoneService Fix Test</h1>\n";

try {
    $timezoneService = $container->get('App\Service\TimezoneService');
    
    // Create a test DateTime that simulates what comes from the database
    // This represents 2026-02-12 00:00:00 as stored in the database (Philippines time)
    $testDateTime = new DateTime('2026-02-12 00:00:00');
    
    echo "<h2>Test Input</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Property</th><th>Value</th></tr>\n";
    echo "<tr><td>Original DateTime</td><td>{$testDateTime->format('Y-m-d H:i:s')}</td></tr>\n";
    echo "<tr><td>Original Timezone</td><td>{$testDateTime->getTimezone()->getName()}</td></tr>\n";
    echo "</table>\n";
    
    // Test the convertFromDatabase method
    $convertedDateTime = $timezoneService->convertFromDatabase($testDateTime);
    
    echo "<h2>TimezoneService Results</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Method</th><th>Result</th><th>Timezone</th><th>Expected</th><th>Status</th></tr>\n";
    
    $convertedResult = $convertedDateTime->format('Y-m-d H:i:s T');
    $convertedStatus = (strpos($convertedResult, '00:00:00') !== false && strpos($convertedResult, 'PST') !== false) ? '✅ PASS' : '❌ FAIL';
    
    echo "<tr><td>convertFromDatabase</td><td><strong>$convertedResult</strong></td><td>{$convertedDateTime->getTimezone()->getName()}</td><td>2026-02-12 00:00:00 PST</td><td>$convertedStatus</td></tr>\n";
    
    // Test formatForFrontend
    $frontendFormat = $timezoneService->formatForFrontend($testDateTime);
    $frontendStatus = ($frontendFormat === '2026-02-12T00:00') ? '✅ PASS' : '❌ FAIL';
    
    echo "<tr><td>formatForFrontend</td><td><strong>$frontendFormat</strong></td><td>-</td><td>2026-02-12T00:00</td><td>$frontendStatus</td></tr>\n";
    
    // Test formatForDisplay
    $displayFormat = $timezoneService->formatForDisplay($testDateTime, 'g:i A');
    $displayStatus = ($displayFormat === '12:00 AM') ? '✅ PASS' : '❌ FAIL';
    
    echo "<tr><td>formatForDisplay</td><td><strong>$displayFormat</strong></td><td>-</td><td>12:00 AM</td><td>$displayStatus</td></tr>\n";
    
    echo "</table>\n";
    
    // Test the ISO format that goes to the calendar
    $isoFormat = $convertedDateTime->format('c');
    $isoStatus = (strpos($isoFormat, '2026-02-12T00:00:00') !== false) ? '✅ PASS' : '❌ FAIL';
    
    echo "<h2>Calendar API Format Test</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Format</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
    echo "<tr><td>ISO 8601 (for calendar)</td><td><strong>$isoFormat</strong></td><td>2026-02-12T00:00:00+08:00</td><td>$isoStatus</td></tr>\n";
    echo "</table>\n";
    
    echo "<h2>🔍 Analysis</h2>\n";
    echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    
    if ($convertedStatus === '✅ PASS' && $frontendStatus === '✅ PASS' && $displayStatus === '✅ PASS' && $isoStatus === '✅ PASS') {
        echo "<h3>✅ TIMEZONE SERVICE FIX SUCCESSFUL!</h3>\n";
        echo "<p>All TimezoneService methods are now working correctly:</p>\n";
        echo "<ul>\n";
        echo "<li>Database time (00:00:00) stays as 00:00:00 ✅</li>\n";
        echo "<li>Frontend format shows T00:00 ✅</li>\n";
        echo "<li>Display format shows 12:00 AM ✅</li>\n";
        echo "<li>ISO format for calendar API is correct ✅</li>\n";
        echo "</ul>\n";
        echo "<p><strong>The calendar should now show 12:00 AM instead of 8:00 AM!</strong></p>\n";
    } else {
        echo "<h3>❌ TIMEZONE SERVICE STILL HAS ISSUES</h3>\n";
        echo "<p>Some methods are not working correctly. Check the results above.</p>\n";
    }
    
    echo "</div>\n";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: {$e->getMessage()}</p>\n";
}

echo "<h2>🔗 Next Steps</h2>\n";
echo "<p>If all tests pass, refresh the calendar page to see the updated display:</p>\n";
echo "<ul>\n";
echo "<li>Hard refresh the calendar page (Ctrl+F5 or Cmd+Shift+R)</li>\n";
echo "<li>Look for Event 76 on February 12, 2026</li>\n";
echo "<li>Both calendar block and hover should now show <strong>12:00 AM</strong></li>\n";
echo "</ul>\n";

$kernel->shutdown();
?>