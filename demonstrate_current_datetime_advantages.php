<?php
// Demonstrate why the current DATETIME structure is superior
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

$pdo = new PDO("mysql:host=localhost;dbname=tesda_calendar;charset=utf8mb4", 'root', '');

echo "<h1>Why Current DATETIME Structure is Perfect</h1>\n";

// Get Event 76 to demonstrate
$stmt = $pdo->prepare("SELECT id, title, start_time, end_time FROM events WHERE id = 76");
$stmt->execute();
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "<h2>Current Structure Advantages</h2>\n";
    
    echo "<h3>1. ✅ Simple Multi-day Event Handling</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Scenario</th><th>Current (DATETIME)</th><th>Separated (DATE + TIME)</th></tr>\n";
    
    // Same day event
    echo "<tr>\n";
    echo "<td>Same Day Event</td>\n";
    echo "<td><code>start_time: 2026-02-12 10:20:00<br>end_time: 2026-02-12 11:20:00</code></td>\n";
    echo "<td><code>start_date: 2026-02-12, start_time: 10:20:00<br>end_date: 2026-02-12, end_time: 11:20:00</code></td>\n";
    echo "</tr>\n";
    
    // Multi-day event
    echo "<tr>\n";
    echo "<td>Multi-day Event</td>\n";
    echo "<td><code>start_time: 2026-02-12 23:00:00<br>end_time: 2026-02-13 01:00:00</code><br>✅ Clear and simple</td>\n";
    echo "<td><code>start_date: 2026-02-12, start_time: 23:00:00<br>end_date: 2026-02-13, end_time: 01:00:00</code><br>❌ Need extra logic to check date difference</td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    
    echo "<h3>2. ✅ Timezone Conversion Simplicity</h3>\n";
    
    // Current approach
    $startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $startPhilippines = clone $startUtc;
    $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    echo "<div style='background-color: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<h4>Current Approach (Simple)</h4>\n";
    echo "<pre>\n";
    echo "// Single line conversion\n";
    echo "\$utc = new DateTime('{$event['start_time']}', new DateTimeZone('UTC'));\n";
    echo "\$philippines = \$utc->setTimezone(new DateTimeZone('Asia/Manila'));\n";
    echo "// Result: {$startPhilippines->format('Y-m-d H:i:s T')}\n";
    echo "</pre>\n";
    echo "</div>\n";
    
    echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<h4>Separated Approach (Complex)</h4>\n";
    echo "<pre>\n";
    echo "// Multiple steps, error-prone\n";
    echo "\$dateTime = \$event['start_date'] . ' ' . \$event['start_time'];\n";
    echo "\$utc = new DateTime(\$dateTime, new DateTimeZone('UTC'));\n";
    echo "\$philippines = \$utc->setTimezone(new DateTimeZone('Asia/Manila'));\n";
    echo "// Need to validate date+time combination\n";
    echo "// What if date is null but time isn't?\n";
    echo "// What if time is '25:00:00' (invalid)?\n";
    echo "</pre>\n";
    echo "</div>\n";
    
    echo "<h3>3. ✅ Database Query Efficiency</h3>\n";
    
    echo "<div style='background-color: #d4edda; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<h4>Current: Simple Range Queries</h4>\n";
    echo "<pre>\n";
    echo "-- Find events in February 2026\n";
    echo "SELECT * FROM events \n";
    echo "WHERE start_time >= '2026-02-01 00:00:00' \n";
    echo "  AND start_time < '2026-03-01 00:00:00';\n";
    echo "\n";
    echo "-- Find overlapping events\n";
    echo "SELECT * FROM events \n";
    echo "WHERE start_time < '2026-02-12 11:20:00' \n";
    echo "  AND end_time > '2026-02-12 10:20:00';\n";
    echo "</pre>\n";
    echo "</div>\n";
    
    echo "<div style='background-color: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 5px;'>\n";
    echo "<h4>Separated: Complex Queries</h4>\n";
    echo "<pre>\n";
    echo "-- Find events in February 2026 (complex!)\n";
    echo "SELECT * FROM events \n";
    echo "WHERE (start_date > '2026-02-01' OR \n";
    echo "       (start_date = '2026-02-01' AND start_time >= '00:00:00'))\n";
    echo "  AND (start_date < '2026-03-01' OR \n";
    echo "       (start_date = '2026-03-01' AND start_time < '00:00:00'));\n";
    echo "\n";
    echo "-- Find overlapping events (very complex!)\n";
    echo "SELECT * FROM events \n";
    echo "WHERE (start_date < '2026-02-12' OR \n";
    echo "       (start_date = '2026-02-12' AND start_time < '11:20:00'))\n";
    echo "  AND (end_date > '2026-02-12' OR \n";
    echo "       (end_date = '2026-02-12' AND end_time > '10:20:00'));\n";
    echo "</pre>\n";
    echo "</div>\n";
    
    echo "<h3>4. ✅ Calendar Integration</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Library</th><th>Current (DATETIME)</th><th>Separated (DATE + TIME)</th></tr>\n";
    echo "<tr><td>FullCalendar</td><td>✅ Direct mapping</td><td>❌ Need to combine fields</td></tr>\n";
    echo "<tr><td>Google Calendar API</td><td>✅ ISO 8601 format</td><td>❌ Manual formatting</td></tr>\n";
    echo "<tr><td>iCal Export</td><td>✅ Standard format</td><td>❌ Custom conversion</td></tr>\n";
    echo "</table>\n";
    
    echo "<h3>5. ✅ Real-world Event Examples</h3>\n";
    
    // Test different event scenarios
    $scenarios = [
        [
            'name' => 'Regular Meeting',
            'start' => '2026-02-12 10:20:00',
            'end' => '2026-02-12 11:20:00',
            'complexity' => 'Simple'
        ],
        [
            'name' => 'Overnight Event',
            'start' => '2026-02-12 23:00:00',
            'end' => '2026-02-13 02:00:00',
            'complexity' => 'Medium'
        ],
        [
            'name' => 'Multi-day Conference',
            'start' => '2026-02-12 09:00:00',
            'end' => '2026-02-14 17:00:00',
            'complexity' => 'Complex'
        ]
    ];
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Event Type</th><th>Current Structure</th><th>Separated Structure</th><th>Winner</th></tr>\n";
    
    foreach ($scenarios as $scenario) {
        $startUtc = new DateTime($scenario['start'], new DateTimeZone('UTC'));
        $endUtc = new DateTime($scenario['end'], new DateTimeZone('UTC'));
        $startPh = clone $startUtc; $startPh->setTimezone(new DateTimeZone('Asia/Manila'));
        $endPh = clone $endUtc; $endPh->setTimezone(new DateTimeZone('Asia/Manila'));
        
        echo "<tr>\n";
        echo "<td><strong>{$scenario['name']}</strong><br><small>{$scenario['complexity']}</small></td>\n";
        echo "<td>\n";
        echo "✅ <code>start_time: {$scenario['start']}</code><br>\n";
        echo "✅ <code>end_time: {$scenario['end']}</code><br>\n";
        echo "<small>Display: {$startPh->format('M j, g:i A')} - {$endPh->format('M j, g:i A')}</small>\n";
        echo "</td>\n";
        echo "<td>\n";
        echo "❌ <code>start_date: {$startUtc->format('Y-m-d')}, start_time: {$startUtc->format('H:i:s')}</code><br>\n";
        echo "❌ <code>end_date: {$endUtc->format('Y-m-d')}, end_time: {$endUtc->format('H:i:s')}</code><br>\n";
        echo "<small>Need complex logic to combine & convert</small>\n";
        echo "</td>\n";
        echo "<td>✅ Current</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

echo "<h2>🎯 Recommendation: Keep Current Structure</h2>\n";
echo "<div style='background-color: #cce5ff; padding: 20px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<h3>Your current DATETIME structure is:</h3>\n";
echo "<ul>\n";
echo "<li>✅ <strong>Industry standard</strong> - Used by all major calendar systems</li>\n";
echo "<li>✅ <strong>Simple to work with</strong> - Single field, easy conversions</li>\n";
echo "<li>✅ <strong>Efficient queries</strong> - Fast database operations</li>\n";
echo "<li>✅ <strong>Multi-day capable</strong> - Handles complex events</li>\n";
echo "<li>✅ <strong>Library compatible</strong> - Works with all calendar libraries</li>\n";
echo "<li>✅ <strong>Already working</strong> - Your timezone system is perfect!</li>\n";
echo "</ul>\n";
echo "</div>\n";

echo "<h2>When to Use Separated Date/Time</h2>\n";
echo "<div style='background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
echo "<p><strong>Only consider separated fields for:</strong></p>\n";
echo "<ul>\n";
echo "<li>🕒 <strong>Business hours</strong>: \"Store opens 09:00-17:00 daily\"</li>\n";
echo "<li>🔄 <strong>Recurring schedules</strong>: \"Team meeting every Monday 14:00\"</li>\n";
echo "<li>⏰ <strong>Time-only events</strong>: \"Lunch break 12:00-13:00\"</li>\n";
echo "</ul>\n";
echo "<p><strong>NOT for specific calendar events like yours!</strong></p>\n";
echo "</div>\n";
?>