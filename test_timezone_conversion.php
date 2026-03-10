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

// Get Event 68 times
$stmt = $pdo->query("
    SELECT 
        id,
        title,
        start_time,
        end_time
    FROM events 
    WHERE id = 68
");

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "=== Event 68 Timezone Analysis ===\n";
    echo "Event Title: " . $event['title'] . "\n";
    echo "Database Start Time (UTC): " . $event['start_time'] . "\n";
    echo "Database End Time (UTC): " . $event['end_time'] . "\n\n";
    
    // Convert to Philippines timezone like the TimezoneService does
    $startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $endUtc = new DateTime($event['end_time'], new DateTimeZone('UTC'));
    
    // Convert to Philippines timezone
    $startPhilippines = clone $startUtc;
    $startPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    $endPhilippines = clone $endUtc;
    $endPhilippines->setTimezone(new DateTimeZone('Asia/Manila'));
    
    echo "=== Server-side Conversion (like CalendarController) ===\n";
    echo "Start Time Philippines: " . $startPhilippines->format('Y-m-d H:i:s T') . "\n";
    echo "End Time Philippines: " . $endPhilippines->format('Y-m-d H:i:s T') . "\n";
    echo "Start Time ISO (sent to browser): " . $startPhilippines->format('c') . "\n";
    echo "End Time ISO (sent to browser): " . $endPhilippines->format('c') . "\n\n";
    
    echo "=== Edit Form Conversion (like EventController) ===\n";
    echo "Start Time for Form Input: " . $startPhilippines->format('Y-m-d\TH:i') . "\n";
    echo "End Time for Form Input: " . $endPhilippines->format('Y-m-d\TH:i') . "\n\n";
    
    echo "=== What Browser Sees ===\n";
    echo "If browser timezone is different from Asia/Manila, it will interpret the ISO datetime differently.\n";
    echo "The ISO format includes timezone info (+08:00), so browser should show correct time.\n";
    echo "But if browser is in different timezone, toLocaleTimeString() will convert it again.\n";
} else {
    echo "Event 68 not found\n";
}
?>