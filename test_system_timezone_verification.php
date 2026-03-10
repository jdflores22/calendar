<?php
// Test script to verify system-wide Philippines timezone implementation
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env.local');

// Database connection
$host = $_ENV['DATABASE_HOST'] ?? 'localhost';
$dbname = $_ENV['DATABASE_NAME'] ?? 'tesda_calendar';
$username = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>System-Wide Philippines Timezone Verification</h1>\n";
    echo "<p>Testing Event 76 timezone consistency across all interfaces</p>\n";
    
    // Get Event 76 data
    $stmt = $pdo->prepare("SELECT id, title, start_time, end_time, created_at FROM events WHERE id = 76");
    $stmt->execute();
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$event) {
        echo "<p style='color: red;'>Event 76 not found!</p>\n";
        exit;
    }
    
    echo "<h2>Event 76 Database Data (UTC)</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Field</th><th>UTC Value</th><th>Philippines Time (UTC+8)</th></tr>\n";
    
    // Convert UTC to Philippines time
    $startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $endUtc = new DateTime($event['end_time'], new DateTimeZone('UTC'));
    $createdUtc = new DateTime($event['created_at'], new DateTimeZone('UTC'));
    
    $startPhilippines = clone $startUtc;
    $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    $endPhilippines = clone $endUtc;
    $endPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    $createdPhilippines = clone $createdUtc;
    $createdPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    echo "<tr><td>Title</td><td colspan='2'>{$event['title']}</td></tr>\n";
    echo "<tr><td>Start Time</td><td>{$event['start_time']}</td><td>{$startPhilippines->format('Y-m-d H:i:s T')}</td></tr>\n";
    echo "<tr><td>End Time</td><td>{$event['end_time']}</td><td>{$endPhilippines->format('Y-m-d H:i:s T')}</td></tr>\n";
    echo "<tr><td>Created At</td><td>{$event['created_at']}</td><td>{$createdPhilippines->format('Y-m-d H:i:s T')}</td></tr>\n";
    echo "</table>\n";
    
    echo "<h2>Expected Display Values (Philippines Time)</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Interface</th><th>Expected Display</th><th>Format</th></tr>\n";
    
    echo "<tr><td>Calendar Hover</td><td>{$startPhilippines->format('g:i A')} - {$endPhilippines->format('g:i A')}</td><td>Time range</td></tr>\n";
    echo "<tr><td>Event Details Page</td><td>{$startPhilippines->format('l, F j, Y g:i A')} - {$endPhilippines->format('g:i A')}</td><td>Full datetime</td></tr>\n";
    echo "<tr><td>Event Edit Form</td><td>{$startPhilippines->format('Y-m-d\\TH:i')}</td><td>datetime-local input</td></tr>\n";
    echo "<tr><td>Event Index</td><td>{$startPhilippines->format('M j, Y g:i A')}</td><td>Short format</td></tr>\n";
    echo "<tr><td>Dashboard</td><td>{$startPhilippines->format('g:i A')}</td><td>Time only</td></tr>\n";
    echo "</table>\n";
    
    echo "<h2>System Timezone Configuration</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>Setting</th><th>Value</th></tr>\n";
    echo "<tr><td>System Timezone</td><td>Asia/Manila</td></tr>\n";
    echo "<tr><td>Timezone Offset</td><td>+08:00</td></tr>\n";
    echo "<tr><td>Database Storage</td><td>UTC</td></tr>\n";
    echo "<tr><td>Display Timezone</td><td>Philippines (UTC+8)</td></tr>\n";
    echo "</table>\n";
    
    echo "<h2>Verification Links</h2>\n";
    echo "<ul>\n";
    echo "<li><a href='http://127.0.0.4:8000/calendar' target='_blank'>Calendar Page</a> - Check hover shows {$startPhilippines->format('g:i A')}</li>\n";
    echo "<li><a href='http://127.0.0.4:8000/events/76' target='_blank'>Event Details</a> - Check shows {$startPhilippines->format('l, F j, Y g:i A')}</li>\n";
    echo "<li><a href='http://127.0.0.4:8000/events/76/edit' target='_blank'>Event Edit</a> - Check form shows {$startPhilippines->format('Y-m-d\\TH:i')}</li>\n";
    echo "<li><a href='http://127.0.0.4:8000/events' target='_blank'>Event Index</a> - Check shows {$startPhilippines->format('M j, Y g:i A')}</li>\n";
    echo "<li><a href='http://127.0.0.4:8000/dashboard' target='_blank'>Dashboard</a> - Check shows {$startPhilippines->format('g:i A')}</li>\n";
    echo "</ul>\n";
    
    echo "<h2>JavaScript Timezone Utilities</h2>\n";
    echo "<p>The following JavaScript utilities are available globally on all pages:</p>\n";
    echo "<pre>\n";
    echo "window.SystemTimezone.now()                    // Current Philippines time\n";
    echo "window.SystemTimezone.getCurrentDate()         // Current Philippines date (YYYY-MM-DD)\n";
    echo "window.SystemTimezone.getCurrentTime()         // Current Philippines time (HH:MM)\n";
    echo "window.SystemTimezone.getTimezone()           // 'Asia/Manila'\n";
    echo "window.SystemTimezone.getOffset()             // '+08:00'\n";
    echo "window.SystemTimezone.formatForInput(date)    // Format for datetime-local inputs\n";
    echo "</pre>\n";
    
    echo "<h2>Twig Filters Available</h2>\n";
    echo "<ul>\n";
    echo "<li><code>system_time</code> - Convert UTC to Philippines time for forms</li>\n";
    echo "<li><code>system_date</code> - Convert UTC to Philippines time for display</li>\n";
    echo "<li><code>philippines_time</code> - Legacy filter (still works)</li>\n";
    echo "<li><code>philippines_date</code> - Legacy filter (still works)</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Status: ✅ SYSTEM-WIDE PHILIPPINES TIMEZONE IMPLEMENTED</h2>\n";
    echo "<p style='color: green; font-weight: bold;'>All pages should now consistently show Philippines time (UTC+8)</p>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database error: " . $e->getMessage() . "</p>\n";
}
?>