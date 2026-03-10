<?php
// Test the Twig filters directly to see if they're working
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Service\TimezoneService;
use App\Twig\TimezoneExtension;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

echo "<h1>🧪 Testing Twig Filters Directly</h1>\n";

try {
    // Create TimezoneService
    $timezoneService = new TimezoneService();
    
    // Create TimezoneExtension
    $timezoneExtension = new TimezoneExtension($timezoneService);
    
    echo "<h2>✅ Services Created Successfully</h2>\n";
    echo "<p>TimezoneService and TimezoneExtension instantiated without errors.</p>\n";
    
    // Test the filters
    $testDateTime = new DateTime('2026-02-12 00:00:00', new DateTimeZone('UTC'));
    
    echo "<h2>Testing Filters with Event 76 Time</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Test</th><th>Input (UTC)</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
    
    // Test system_time filter (for datetime-local inputs)
    try {
        $systemTimeResult = $timezoneExtension->convertToSystemTime($testDateTime);
        $expected = '2026-02-12T08:00';
        $status = ($systemTimeResult === $expected) ? '✅ PASS' : '❌ FAIL';
        echo "<tr><td>system_time filter</td><td>2026-02-12 00:00:00</td><td><strong>$systemTimeResult</strong></td><td>$expected</td><td>$status</td></tr>\n";
    } catch (Exception $e) {
        echo "<tr><td>system_time filter</td><td>2026-02-12 00:00:00</td><td style='color: red;'>ERROR: {$e->getMessage()}</td><td>2026-02-12T08:00</td><td>❌ FAIL</td></tr>\n";
    }
    
    // Test system_date filter (for display)
    try {
        $systemDateResult = $timezoneExtension->formatSystemDate($testDateTime, 'g:i A');
        $expected = '8:00 AM';
        $status = ($systemDateResult === $expected) ? '✅ PASS' : '❌ FAIL';
        echo "<tr><td>system_date filter</td><td>2026-02-12 00:00:00</td><td><strong>$systemDateResult</strong></td><td>$expected</td><td>$status</td></tr>\n";
    } catch (Exception $e) {
        echo "<tr><td>system_date filter</td><td>2026-02-12 00:00:00</td><td style='color: red;'>ERROR: {$e->getMessage()}</td><td>8:00 AM</td><td>❌ FAIL</td></tr>\n";
    }
    
    // Test full date format
    try {
        $fullDateResult = $timezoneExtension->formatSystemDate($testDateTime, 'l, F j, Y g:i A');
        $expected = 'Thursday, February 12, 2026 8:00 AM';
        $status = ($fullDateResult === $expected) ? '✅ PASS' : '❌ FAIL';
        echo "<tr><td>Full date format</td><td>2026-02-12 00:00:00</td><td><strong>$fullDateResult</strong></td><td>$expected</td><td>$status</td></tr>\n";
    } catch (Exception $e) {
        echo "<tr><td>Full date format</td><td>2026-02-12 00:00:00</td><td style='color: red;'>ERROR: {$e->getMessage()}</td><td>Thursday, February 12, 2026 8:00 AM</td><td>❌ FAIL</td></tr>\n";
    }
    
    echo "</table>\n";
    
    // Test TimezoneService directly
    echo "<h2>Testing TimezoneService Directly</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Method</th><th>Input</th><th>Result</th><th>Expected</th><th>Status</th></tr>\n";
    
    try {
        $convertedTime = $timezoneService->convertFromUtc($testDateTime);
        $result = $convertedTime->format('Y-m-d H:i:s T');
        $expected = '2026-02-12 08:00:00 PST';
        $status = (strpos($result, '08:00:00') !== false) ? '✅ PASS' : '❌ FAIL';
        echo "<tr><td>convertFromUtc</td><td>2026-02-12 00:00:00 UTC</td><td><strong>$result</strong></td><td>$expected</td><td>$status</td></tr>\n";
    } catch (Exception $e) {
        echo "<tr><td>convertFromUtc</td><td>2026-02-12 00:00:00 UTC</td><td style='color: red;'>ERROR: {$e->getMessage()}</td><td>2026-02-12 08:00:00 PST</td><td>❌ FAIL</td></tr>\n";
    }
    
    try {
        $frontendFormat = $timezoneService->formatForFrontend($testDateTime);
        $expected = '2026-02-12T08:00';
        $status = ($frontendFormat === $expected) ? '✅ PASS' : '❌ FAIL';
        echo "<tr><td>formatForFrontend</td><td>2026-02-12 00:00:00 UTC</td><td><strong>$frontendFormat</strong></td><td>$expected</td><td>$status</td></tr>\n";
    } catch (Exception $e) {
        echo "<tr><td>formatForFrontend</td><td>2026-02-12 00:00:00 UTC</td><td style='color: red;'>ERROR: {$e->getMessage()}</td><td>2026-02-12T08:00</td><td>❌ FAIL</td></tr>\n";
    }
    
    echo "</table>\n";
    
    // Test timezone configuration
    echo "<h2>Timezone Configuration</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Setting</th><th>Value</th></tr>\n";
    echo "<tr><td>System Timezone</td><td>{$timezoneService->getSystemTimezone()}</td></tr>\n";
    echo "<tr><td>System Offset</td><td>{$timezoneService->getSystemTimezoneOffset()}</td></tr>\n";
    echo "<tr><td>Current System Time</td><td>{$timezoneService->now()->format('Y-m-d H:i:s T')}</td></tr>\n";
    echo "</table>\n";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    echo "<h2>❌ Error Creating Services</h2>\n";
    echo "<p>Error: {$e->getMessage()}</p>\n";
    echo "<p>This indicates a problem with the TimezoneService or TimezoneExtension classes.</p>\n";
    echo "</div>\n";
}

echo "<h2>🔍 Diagnosis</h2>\n";
echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>If All Tests Pass:</h3>\n";
echo "<ul>\n";
echo "<li>✅ The Twig filters are working correctly in isolation</li>\n";
echo "<li>❓ The issue might be in how Symfony is registering the Twig extension</li>\n";
echo "<li>❓ There might be a caching issue</li>\n";
echo "<li>❓ The templates might not be using the filters correctly</li>\n";
echo "</ul>\n";

echo "<h3>If Tests Fail:</h3>\n";
echo "<ul>\n";
echo "<li>❌ There's a bug in the TimezoneService or TimezoneExtension</li>\n";
echo "<li>❌ The timezone conversion logic is incorrect</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>🧪 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Check the results above</li>\n";
echo "<li>If tests pass, check Symfony's Twig extension registration</li>\n";
echo "<li>Clear cache: <code>php bin/console cache:clear</code></li>\n";
echo "<li>Test the actual event pages to see what they display</li>\n";
echo "</ol>\n";
?>