<?php
require_once 'vendor/autoload.php';
use Symfony\Component\Dotenv\Dotenv;
use App\Service\TimezoneService;
use App\Twig\TimezoneExtension;

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

// Get Event 76
$stmt = $pdo->query("SELECT start_time, end_time FROM events WHERE id = 76");
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "=== Event 76 Twig Filter Test ===\n";
    echo "Database Start Time (UTC): " . $event['start_time'] . "\n";
    echo "Database End Time (UTC): " . $event['end_time'] . "\n\n";
    
    // Test the Twig filters
    $timezoneService = new TimezoneService();
    $twigExtension = new TimezoneExtension($timezoneService);
    
    $startUtc = new DateTime($event['start_time'], new DateTimeZone('UTC'));
    $endUtc = new DateTime($event['end_time'], new DateTimeZone('UTC'));
    
    echo "=== Twig Filter Results ===\n";
    echo "philippines_time (for forms): " . $twigExtension->convertToPhilippinesTime($startUtc) . "\n";
    echo "philippines_date (for display): " . $twigExtension->formatPhilippinesDate($startUtc, 'g:i A') . "\n";
    echo "philippines_date (full): " . $twigExtension->formatPhilippinesDate($startUtc, 'l, F j, Y g:i A') . "\n\n";
    
    echo "=== Expected vs Actual ===\n";
    echo "Expected in event details: Thursday, February 12, 2026 8:01 AM - 10:30 AM\n";
    echo "Expected in edit form: 2026-02-12T08:01\n";
    echo "Actual philippines_time: " . $twigExtension->convertToPhilippinesTime($startUtc) . "\n";
    echo "Actual philippines_date: " . $twigExtension->formatPhilippinesDate($startUtc, 'l, F j, Y g:i A') . "\n";
    
    // Check if the issue is with the filter or the template
    if ($twigExtension->formatPhilippinesDate($startUtc, 'g:i A') === '8:01 AM') {
        echo "\n✅ Twig filters are working correctly!\n";
        echo "❌ The issue must be that the templates are NOT using the filters\n";
    } else {
        echo "\n❌ Twig filters are not working correctly\n";
    }
} else {
    echo "Event 76 not found\n";
}
?>