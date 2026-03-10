<?php
require_once 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
use App\Service\TimezoneService;

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
    echo "=== Event 68 Edit Form Debug ===\n";
    echo "Event Title: " . $event['title'] . "\n";
    echo "Database Start Time (UTC): " . $event['start_time'] . "\n";
    echo "Database End Time (UTC): " . $event['end_time'] . "\n\n";
    
    // Simulate what TimezoneService does
    $timezoneService = new TimezoneService();
    
    $startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $endUtc = new DateTime($event['end_time'], new DateTimeZone('UTC'));
    
    echo "=== TimezoneService formatForFrontend() ===\n";
    echo "Start Time Formatted: " . $timezoneService->formatForFrontend($startUtc) . "\n";
    echo "End Time Formatted: " . $timezoneService->formatForFrontend($endUtc) . "\n\n";
    
    echo "=== Manual Conversion Check ===\n";
    $startPhilippines = $timezoneService->convertFromUtc($startUtc);
    $endPhilippines = $timezoneService->convertFromUtc($endUtc);
    
    echo "Start Philippines: " . $startPhilippines->format('Y-m-d H:i:s T') . "\n";
    echo "End Philippines: " . $endPhilippines->format('Y-m-d H:i:s T') . "\n";
    echo "Start Form Format: " . $startPhilippines->format('Y-m-d\TH:i') . "\n";
    echo "End Form Format: " . $endPhilippines->format('Y-m-d\TH:i') . "\n\n";
    
    echo "=== Expected vs Actual ===\n";
    echo "Expected in form: 2026-02-20T02:00 (2:00 AM Philippines time)\n";
    echo "If showing 12:00, there's a timezone interpretation issue in the browser\n";
} else {
    echo "Event 68 not found\n";
}
?>