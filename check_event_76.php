<?php
require_once 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->load('.env.local');

$databaseUrl = $_ENV['DATABASE_URL'] ?? '';
$parsed = parse_url($databaseUrl);
$host = $parsed['host'] ?? 'localhost';
$dbname = ltrim($parsed['path'] ?? '', '/');
$username = $parsed['user'] ?? 'root';
$password = $parsed['pass'] ?? '';
$port = $parsed['port'] ?? 3306;

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Get Event 76 with all details
$stmt = $pdo->query("
    SELECT 
        id,
        title,
        start_time,
        end_time,
        location,
        creator_id,
        office_id
    FROM events 
    WHERE id = 76
");

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "=== Event 76 Database Analysis ===\n";
    echo "Event ID: " . $event['id'] . "\n";
    echo "Title: " . $event['title'] . "\n";
    echo "Database Start Time (UTC): " . $event['start_time'] . "\n";
    echo "Database End Time (UTC): " . $event['end_time'] . "\n";
    echo "Location: " . ($event['location'] ?? 'No location') . "\n\n";
    
    // Convert to Philippines timezone
    $startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $endUtc = new DateTime($event['end_time'], new DateTimeZone('UTC'));
    
    $startPhilippines = clone $startUtc;
    $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    $endPhilippines = clone $endUtc;
    $endPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    echo "=== Timezone Conversions ===\n";
    echo "Start Time Philippines: " . $startPhilippines->format('Y-m-d H:i:s T') . " (" . $startPhilippines->format('g:i A') . ")\n";
    echo "End Time Philippines: " . $endPhilippines->format('Y-m-d H:i:s T') . " (" . $endPhilippines->format('g:i A') . ")\n\n";
    
    echo "=== What Should Display Where ===\n";
    echo "Calendar Hover (Philippines time): " . $startPhilippines->format('g:i A') . " - " . $endPhilippines->format('g:i A') . "\n";
    echo "Event Details (Philippines time): " . $startPhilippines->format('g:i A') . " - " . $endPhilippines->format('g:i A') . "\n";
    echo "Edit Form (Philippines time): " . $startPhilippines->format('Y-m-d\TH:i') . " to " . $endPhilippines->format('Y-m-d\TH:i') . "\n\n";
    
    echo "=== Current Issue Analysis ===\n";
    echo "If calendar shows 8:01 AM and edit form shows 12:01 AM:\n";
    echo "- Calendar is correctly showing Philippines time (8:01 AM)\n";
    echo "- Edit form is incorrectly showing UTC time (12:01 AM)\n";
    echo "- Both should show Philippines time (8:01 AM)\n";
} else {
    echo "Event 76 not found\n";
}
?>