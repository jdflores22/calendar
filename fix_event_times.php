<?php
// Fix event times that were affected by timezone changes
require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

echo "<h1>🔧 Fix Event Times</h1>\n";

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
    
    // Get all events that need fixing
    $stmt = $pdo->prepare("SELECT id, title, start_time, end_time FROM events ORDER BY id");
    $stmt->execute();
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Events Before Fix</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>ID</th><th>Title</th><th>Start Time</th><th>End Time</th></tr>\n";
    
    foreach ($events as $event) {
        echo "<tr>\n";
        echo "<td>{$event['id']}</td>\n";
        echo "<td>{$event['title']}</td>\n";
        echo "<td>{$event['start_time']}</td>\n";
        echo "<td>{$event['end_time']}</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
    
    // For Event 76, we know it should be 00:00:00 to 02:00:00 (Philippines time)
    // The current value is 06:00:00 to 09:00:00, which suggests it was shifted by +6 hours
    // This happened because the timezone change converted Philippines time to a different interpretation
    
    echo "<h2>Fixing Event 76</h2>\n";
    
    // Reset Event 76 to the correct Philippines time
    $correctStartTime = '2026-02-12 00:00:00';  // 12:00 AM Philippines time
    $correctEndTime = '2026-02-12 02:00:00';    // 2:00 AM Philippines time
    
    $updateStmt = $pdo->prepare("UPDATE events SET start_time = ?, end_time = ? WHERE id = ?");
    $result = $updateStmt->execute([$correctStartTime, $correctEndTime, 76]);
    
    if ($result) {
        echo "<p style='color: green;'>✅ Event 76 times corrected</p>\n";
        echo "<ul>\n";
        echo "<li>Start time: $correctStartTime (12:00 AM Philippines)</li>\n";
        echo "<li>End time: $correctEndTime (2:00 AM Philippines)</li>\n";
        echo "</ul>\n";
    } else {
        echo "<p style='color: red;'>❌ Failed to update Event 76</p>\n";
    }
    
    // Verify the fix
    $verifyStmt = $pdo->prepare("SELECT id, title, start_time, end_time FROM events WHERE id = ?");
    $verifyStmt->execute([76]);
    $fixedEvent = $verifyStmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Event 76 After Fix</h2>\n";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>\n";
    echo "<tr><th>ID</th><th>Title</th><th>Start Time</th><th>End Time</th><th>Expected Display</th></tr>\n";
    echo "<tr>\n";
    echo "<td>{$fixedEvent['id']}</td>\n";
    echo "<td>{$fixedEvent['title']}</td>\n";
    echo "<td><strong>{$fixedEvent['start_time']}</strong></td>\n";
    echo "<td><strong>{$fixedEvent['end_time']}</strong></td>\n";
    echo "<td>12:00 AM - 2:00 AM (Philippines)</td>\n";
    echo "</tr>\n";
    echo "</table>\n";
    
    echo "<h2>✅ Fix Complete</h2>\n";
    echo "<p>Event 76 has been restored to the correct Philippines time values.</p>\n";
    echo "<p>Now test the application:</p>\n";
    echo "<ul>\n";
    echo "<li><a href='http://127.0.0.4:8000/events/76' target='_blank'>Event 76 Details</a> - should show 12:00 AM</li>\n";
    echo "<li><a href='http://127.0.0.4:8000/events/76/edit' target='_blank'>Event 76 Edit</a> - should show 12:00 AM</li>\n";
    echo "<li><a href='http://127.0.0.4:8000/calendar' target='_blank'>Calendar</a> - should show 12:00 AM</li>\n";
    echo "</ul>\n";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>\n";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>\n";
}
?>