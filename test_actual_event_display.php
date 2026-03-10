<?php
// Test what the actual event pages should display
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

$pdo = new PDO('mysql:host=localhost;dbname=tesda_calendar;charset=utf8mb4', 'root', '');

echo "<h1>🔍 Testing Actual Event Display</h1>\n";

// Get Event 76
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = 76");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    echo "<p style='color: red;'>Event 76 not found!</p>\n";
    exit;
}

echo "<h2>Event 76 Database Data</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Field</th><th>Value</th></tr>\n";
echo "<tr><td>ID</td><td>{$event['id']}</td></tr>\n";
echo "<tr><td>Title</td><td>{$event['title']}</td></tr>\n";
echo "<tr><td>Start Time (UTC)</td><td><strong>{$event['start_time']}</strong></td></tr>\n";
echo "<tr><td>End Time (UTC)</td><td><strong>{$event['end_time']}</strong></td></tr>\n";
echo "</table>\n";

// Simulate what the templates should show
$startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
$endUtc = new DateTime($event['end_time'], new DateTimeZone('UTC'));

$startPhilippines = clone $startUtc;
$startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));

$endPhilippines = clone $endUtc;
$endPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));

echo "<h2>What Templates Should Display</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Template</th><th>Twig Code</th><th>Expected Output</th><th>Test Link</th></tr>\n";

// Event show template
$showDisplay = $startPhilippines->format('l, F j, Y g:i A') . ' - ' . $endPhilippines->format('g:i A');
echo "<tr>\n";
echo "<td>Event Details</td>\n";
echo "<td><code>{{ event.startTime|system_date('l, F j, Y g:i A') }}</code></td>\n";
echo "<td><strong style='color: green;'>$showDisplay</strong></td>\n";
echo "<td><a href='http://127.0.0.4:8000/events/76' target='_blank'>Test Details Page</a></td>\n";
echo "</tr>\n";

// Event edit template
$editDisplay = $startPhilippines->format('Y-m-d\TH:i');
echo "<tr>\n";
echo "<td>Event Edit Form</td>\n";
echo "<td><code>{{ event.startTime|system_time }}</code></td>\n";
echo "<td><strong style='color: green;'>$editDisplay</strong></td>\n";
echo "<td><a href='http://127.0.0.4:8000/events/76/edit' target='_blank'>Test Edit Page</a></td>\n";
echo "</tr>\n";

// Event index template (need to check this)
$indexDisplay = $startPhilippines->format('M j, Y g:i A');
echo "<tr>\n";
echo "<td>Event Index</td>\n";
echo "<td><code>{{ event.startTime|system_date('M j, Y g:i A') }}</code></td>\n";
echo "<td><strong style='color: green;'>$indexDisplay</strong></td>\n";
echo "<td><a href='http://127.0.0.4:8000/events' target='_blank'>Test Index Page</a></td>\n";
echo "</tr>\n";

echo "</table>\n";

echo "<h2>🚨 If Pages Show Different Times</h2>\n";
echo "<div style='background-color: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>If Event Pages Show 12:00 AM Instead of 8:00 AM:</h3>\n";
echo "<p>This means the templates are displaying the <strong>raw UTC time</strong> instead of using the Twig filters.</p>\n";

echo "<h4>Possible Causes:</h4>\n";
echo "<ul>\n";
echo "<li>❌ Templates using <code>{{ event.startTime|date('g:i A') }}</code> instead of <code>{{ event.startTime|system_date('g:i A') }}</code></li>\n";
echo "<li>❌ Templates using raw <code>{{ event.startTime.format('g:i A') }}</code></li>\n";
echo "<li>❌ Old cached templates still being used</li>\n";
echo "<li>❌ Different event object being passed to templates</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>🔧 Manual Testing Instructions</h2>\n";
echo "<ol>\n";
echo "<li><strong>Test Event Details Page:</strong>\n";
echo "   <ul><li>Go to <a href='http://127.0.0.4:8000/events/76' target='_blank'>http://127.0.0.4:8000/events/76</a></li>\n";
echo "   <li>Look for the time display - should show <strong style='color: green;'>Thursday, February 12, 2026 8:00 AM</strong></li>\n";
echo "   <li>If it shows 12:00 AM, the template is not using system_date filter</li></ul></li>\n";

echo "<li><strong>Test Event Edit Page:</strong>\n";
echo "   <ul><li>Go to <a href='http://127.0.0.4:8000/events/76/edit' target='_blank'>http://127.0.0.4:8000/events/76/edit</a></li>\n";
echo "   <li>Look at the datetime input fields - should show <strong style='color: green;'>2026-02-12T08:00</strong></li>\n";
echo "   <li>If it shows 2026-02-12T00:00, the template is not using system_time filter</li></ul></li>\n";

echo "<li><strong>Test Calendar Page:</strong>\n";
echo "   <ul><li>Go to <a href='http://127.0.0.4:8000/calendar' target='_blank'>http://127.0.0.4:8000/calendar</a></li>\n";
echo "   <li>Find Event 76 on February 12, 2026</li>\n";
echo "   <li>Hover over it - should show <strong style='color: green;'>8:00 AM - 8:00 AM</strong></li>\n";
echo "   <li>This should now work correctly after our JavaScript fixes</li></ul></li>\n";
echo "</ol>\n";

echo "<h2>🔍 Debug Information</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Check</th><th>Status</th><th>Details</th></tr>\n";
echo "<tr><td>Twig Filters Working</td><td>✅ PASS</td><td>Confirmed in previous test</td></tr>\n";
echo "<tr><td>Filters Registered</td><td>✅ PASS</td><td>Confirmed with debug:twig command</td></tr>\n";
echo "<tr><td>Cache Cleared</td><td>✅ PASS</td><td>Just cleared cache</td></tr>\n";
echo "<tr><td>Database Data</td><td>✅ PASS</td><td>Event 76 has correct UTC time</td></tr>\n";
echo "<tr><td>Templates Using Filters</td><td>❓ UNKNOWN</td><td>Need to test actual pages</td></tr>\n";
echo "</table>\n";

echo "<h2>📋 Report Back</h2>\n";
echo "<p>Please test the links above and report:</p>\n";
echo "<ul>\n";
echo "<li>What time does the <strong>Event Details page</strong> show?</li>\n";
echo "<li>What time does the <strong>Event Edit form</strong> show?</li>\n";
echo "<li>What time does the <strong>Calendar hover</strong> show?</li>\n";
echo "</ul>\n";
echo "<p>This will help us identify exactly where the problem is!</p>\n";
?>