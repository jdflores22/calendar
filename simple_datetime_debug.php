<?php
// Simple diagnostic script to check DateTime objects from database
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

echo "<h1>🔍 Simple DateTime Debug</h1>\n";

// Connect to database directly
$host = $_ENV['DATABASE_HOST'] ?? 'localhost';
$port = $_ENV['DATABASE_PORT'] ?? '3306';
$dbname = $_ENV['DATABASE_NAME'] ?? 'tesda_calendar';
$username = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<p style='color: green;'>✅ Database connection successful</p>\n";
    
    // Get Event 76 data directly from database
    $stmt = $pdo->prepare("SELECT id, title, start_time, end_time FROM events WHERE id = ?");
    $stmt->execute([76]);
    $eventData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$eventData) {
        echo "<p style='color: red;'>❌ Event 76 not found in database</p>\n";
        exit;
    }
    
    echo "<h2>Event 76 Raw Database Data</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>Raw Value</th><th>Type</th></tr>\n";
    echo "<tr><td>ID</td><td>{$eventData['id']}</td><td>Integer</td></tr>\n";
    echo "<tr><td>Title</td><td>{$eventData['title']}</td><td>String</td></tr>\n";
    echo "<tr><td>start_time</td><td><strong>{$eventData['start_time']}</strong></td><td>String (from DB)</td></tr>\n";
    echo "<tr><td>end_time</td><td><strong>{$eventData['end_time']}</strong></td><td>String (from DB)</td></tr>\n";
    echo "</table>\n";
    
    // Create DateTime objects from the raw data
    echo "<h2>DateTime Object Analysis</h2>\n";
    
    // Test 1: Create DateTime without timezone (PHP default)
    $startDefault = new DateTime($eventData['start_time']);
    echo "<h3>Test 1: Default DateTime Creation</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Input</th><th>Timezone</th><th>Display</th><th>UTC Format</th><th>Philippines Format</th></tr>\n";
    
    $startDefaultTz = $startDefault->getTimezone()->getName();
    $startDefaultDisplay = $startDefault->format('Y-m-d H:i:s');
    
    // Convert to UTC
    $startUtc = clone $startDefault;
    $startUtc->setTimezone(new DateTimeZone('UTC'));
    $startUtcDisplay = $startUtc->format('Y-m-d H:i:s T');
    
    // Convert to Philippines
    $startPhilippines = clone $startDefault;
    $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    $startPhilippinesDisplay = $startPhilippines->format('Y-m-d H:i:s T');
    
    echo "<tr><td>{$eventData['start_time']}</td><td><strong>$startDefaultTz</strong></td><td>$startDefaultDisplay</td><td>$startUtcDisplay</td><td><strong style='color: green;'>$startPhilippinesDisplay</strong></td></tr>\n";
    echo "</table>\n";
    
    // Test 2: Create DateTime assuming it's UTC
    echo "<h3>Test 2: Assuming Database Time is UTC</h3>\n";
    $startAsUtc = new DateTime($eventData['start_time'], new DateTimeZone('UTC'));
    $startAsUtcPhilippines = clone $startAsUtc;
    $startAsUtcPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Assumption</th><th>Input</th><th>UTC Format</th><th>Philippines Format</th><th>Expected Result</th></tr>\n";
    echo "<tr><td>DB time is UTC</td><td>{$eventData['start_time']}</td><td>{$startAsUtc->format('Y-m-d H:i:s T')}</td><td><strong style='color: green;'>{$startAsUtcPhilippines->format('Y-m-d H:i:s T')}</strong></td><td>2026-02-12 08:00:00 PST</td></tr>\n";
    echo "</table>\n";
    
    // Test 3: Check PHP's default timezone
    echo "<h3>Test 3: PHP Configuration</h3>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Setting</th><th>Value</th></tr>\n";
    echo "<tr><td>PHP Default Timezone</td><td><strong>" . date_default_timezone_get() . "</strong></td></tr>\n";
    echo "<tr><td>Current Time (PHP default)</td><td>" . date('Y-m-d H:i:s T') . "</td></tr>\n";
    echo "<tr><td>Current Time (UTC)</td><td>" . gmdate('Y-m-d H:i:s') . " UTC</td></tr>\n";
    
    $nowPhilippines = new DateTime('now', new DateTimeZone('Asia/Manila'));
    echo "<tr><td>Current Time (Philippines)</td><td>" . $nowPhilippines->format('Y-m-d H:i:s T') . "</td></tr>\n";
    echo "</table>\n";
    
    echo "<h2>🔍 Analysis</h2>\n";
    echo "<div style='background-color: #d1ecf1; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
    
    if ($startDefaultTz === 'UTC') {
        echo "<h3>✅ PHP Default Timezone is UTC</h3>\n";
        echo "<p>This is good - DateTime objects created from database will be in UTC.</p>\n";
    } else {
        echo "<h3>⚠️ PHP Default Timezone is NOT UTC</h3>\n";
        echo "<p>Current PHP timezone: <strong>$startDefaultTz</strong></p>\n";
        echo "<p>This means DateTime objects from database are being created in <strong>$startDefaultTz</strong> instead of UTC.</p>\n";
        echo "<p><strong>This is likely the root cause of the timezone issue!</strong></p>\n";
    }
    
    $expectedPhilippinesTime = '08:00:00';
    if (strpos($startAsUtcPhilippines->format('H:i:s'), $expectedPhilippinesTime) !== false) {
        echo "<h3>✅ UTC to Philippines Conversion Works</h3>\n";
        echo "<p>When we assume database time is UTC, conversion to Philippines gives correct result: {$startAsUtcPhilippines->format('H:i:s')}</p>\n";
    } else {
        echo "<h3>❌ UTC to Philippines Conversion Issue</h3>\n";
        echo "<p>Expected: 08:00:00, Got: {$startAsUtcPhilippines->format('H:i:s')}</p>\n";
    }
    
    echo "</div>\n";
    
    echo "<h2>🛠️ Recommended Fix</h2>\n";
    echo "<ol>\n";
    if ($startDefaultTz !== 'UTC') {
        echo "<li><strong>Set PHP default timezone to UTC</strong> in php.ini or application bootstrap</li>\n";
        echo "<li>Or ensure Doctrine is configured to handle timezones properly</li>\n";
    }
    echo "<li>Verify that Twig filters are working with the corrected DateTime objects</li>\n";
    echo "<li>Test the event pages again</li>\n";
    echo "</ol>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $e->getMessage() . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}
?>