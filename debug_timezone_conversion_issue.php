<?php
// Debug the timezone conversion issue you described
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

$pdo = new PDO('mysql:host=localhost;dbname=tesda_calendar;charset=utf8mb4', 'root', '');

echo "<h1>🔍 Debugging Timezone Conversion Issue</h1>\n";
echo "<p>You reported: Database shows '2026-02-12 00:00:00', Calendar shows '8:00 AM', Event pages show '12:00 AM'</p>\n";

// Find an event with 00:00:00 time
$stmt = $pdo->query("
    SELECT id, title, start_time, end_time, created_at 
    FROM events 
    WHERE TIME(start_time) = '00:00:00' 
    ORDER BY id DESC 
    LIMIT 5
");

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($events)) {
    echo "<p style='color: orange;'>No events found with 00:00:00 start time. Let me check all events:</p>\n";
    
    $stmt = $pdo->query("
        SELECT id, title, start_time, end_time 
        FROM events 
        ORDER BY id DESC 
        LIMIT 10
    ");
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo "<h2>Events in Database</h2>\n";
echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>ID</th><th>Title</th><th>Start Time (UTC)</th><th>Expected Philippines Time</th><th>Test Links</th></tr>\n";

foreach ($events as $event) {
    // Convert UTC to Philippines time
    $utcTime = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $philippinesTime = clone $utcTime;
    $philippinesTime->setTimezone(new DateTimeZone('Asia/Manila'));
    
    echo "<tr>\n";
    echo "<td>{$event['id']}</td>\n";
    echo "<td>{$event['title']}</td>\n";
    echo "<td><strong>{$event['start_time']}</strong></td>\n";
    echo "<td><strong style='color: green;'>{$philippinesTime->format('Y-m-d H:i:s T')}</strong></td>\n";
    echo "<td>\n";
    echo "<a href='http://127.0.0.4:8000/events/{$event['id']}' target='_blank'>Details</a> | \n";
    echo "<a href='http://127.0.0.4:8000/events/{$event['id']}/edit' target='_blank'>Edit</a>\n";
    echo "</td>\n";
    echo "</tr>\n";
}
echo "</table>\n";

// Test specific case you mentioned
echo "<h2>Testing Your Specific Case</h2>\n";
echo "<div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>If Database shows: 2026-02-12 00:00:00 (UTC)</h3>\n";

$testUtc = new DateTime('2026-02-12 00:00:00', new DateTimeZone('UTC'));
$testPhilippines = clone $testUtc;
$testPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>Conversion</th><th>Result</th><th>Expected Display</th></tr>\n";
echo "<tr><td>UTC to Philippines</td><td><strong>{$testPhilippines->format('Y-m-d H:i:s T')}</strong></td><td>Should show 8:00 AM</td></tr>\n";
echo "<tr><td>For datetime-local input</td><td><strong>{$testPhilippines->format('Y-m-d\\TH:i')}</strong></td><td>Should show 2026-02-12T08:00</td></tr>\n";
echo "<tr><td>For display</td><td><strong>{$testPhilippines->format('g:i A')}</strong></td><td>Should show 8:00 AM</td></tr>\n";
echo "</table>\n";
echo "</div>\n";

// Test the Twig filters manually
echo "<h2>Manual Twig Filter Test</h2>\n";
echo "<p>Let me simulate what the Twig filters should do:</p>\n";

// Simulate system_time filter (for datetime-local inputs)
function simulateSystemTimeFilter($utcDatetime) {
    $utc = new DateTime($utcDatetime, new DateTimeZone('UTC'));
    $philippines = clone $utc;
    $philippines->setTimezone(new DateTimeZone('Asia/Manila'));
    return $philippines->format('Y-m-d\TH:i');
}

// Simulate system_date filter (for display)
function simulateSystemDateFilter($utcDatetime, $format = 'Y-m-d H:i:s') {
    $utc = new DateTime($utcDatetime, new DateTimeZone('UTC'));
    $philippines = clone $utc;
    $philippines->setTimezone(new DateTimeZone('Asia/Manila'));
    return $philippines->format($format);
}

$testCases = [
    '2026-02-12 00:00:00',
    '2026-02-12 02:20:00',
    '2026-02-12 16:30:00'
];

echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
echo "<tr><th>UTC Input</th><th>system_time Filter</th><th>system_date Filter</th><th>Display Format</th></tr>\n";

foreach ($testCases as $testCase) {
    $systemTime = simulateSystemTimeFilter($testCase);
    $systemDate = simulateSystemDateFilter($testCase, 'g:i A');
    $fullDate = simulateSystemDateFilter($testCase, 'l, F j, Y g:i A');
    
    echo "<tr>\n";
    echo "<td>{$testCase}</td>\n";
    echo "<td><strong>{$systemTime}</strong></td>\n";
    echo "<td><strong>{$systemDate}</strong></td>\n";
    echo "<td>{$fullDate}</td>\n";
    echo "</tr>\n";
}
echo "</table>\n";

echo "<h2>🔍 Diagnosis</h2>\n";
echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>Expected Behavior:</h3>\n";
echo "<ul>\n";
echo "<li><strong>Database</strong>: 2026-02-12 00:00:00 (UTC)</li>\n";
echo "<li><strong>Calendar</strong>: 8:00 AM (UTC + 8 hours = Philippines time) ✅</li>\n";
echo "<li><strong>Event Details</strong>: 8:00 AM (using system_date filter) ❓</li>\n";
echo "<li><strong>Event Edit</strong>: 2026-02-12T08:00 (using system_time filter) ❓</li>\n";
echo "</ul>\n";

echo "<h3>If Event Pages Show 12:00 AM Instead of 8:00 AM:</h3>\n";
echo "<ul>\n";
echo "<li>❌ The Twig filters are NOT working correctly</li>\n";
echo "<li>❌ The templates are displaying raw UTC time instead of converted time</li>\n";
echo "<li>❌ There might be an issue with the TimezoneService or TwigExtension</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>🧪 Next Steps</h2>\n";
echo "<ol>\n";
echo "<li>Test the actual event pages with the links above</li>\n";
echo "<li>Check if the Twig filters are registered correctly</li>\n";
echo "<li>Verify the TimezoneService is working</li>\n";
echo "<li>Check for any caching issues</li>\n";
echo "</ol>\n";
?>