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

// Get event 68 with creator's office information
$stmt = $pdo->query("
    SELECT 
        e.id,
        e.title,
        e.office_id as event_office_id,
        eo.name as event_office_name,
        e.creator_id,
        u.email as creator_email,
        u.office_id as creator_office_id,
        co.name as creator_office_name,
        co.color as creator_office_color
    FROM events e
    LEFT JOIN offices eo ON e.office_id = eo.id
    LEFT JOIN users u ON e.creator_id = u.id
    LEFT JOIN offices co ON u.office_id = co.id
    WHERE e.id = 68
");

$event = $stmt->fetch(PDO::FETCH_ASSOC);

if ($event) {
    echo "=== Event 68 Creator Office Analysis ===\n";
    echo "Event Title: " . $event['title'] . "\n";
    echo "Event Office ID: " . ($event['event_office_id'] ?? 'NULL') . "\n";
    echo "Event Office Name: " . ($event['event_office_name'] ?? 'No office assigned') . "\n\n";
    
    echo "Creator Email: " . ($event['creator_email'] ?? 'No creator') . "\n";
    echo "Creator Office ID: " . ($event['creator_office_id'] ?? 'NULL') . "\n";
    echo "Creator Office Name: " . ($event['creator_office_name'] ?? 'No office assigned') . "\n";
    echo "Creator Office Color: " . ($event['creator_office_color'] ?? 'No color') . "\n\n";
    
    if ($event['creator_office_id']) {
        echo "RECOMMENDATION: Event should use creator's office:\n";
        echo "- Office: " . $event['creator_office_name'] . "\n";
        echo "- Color: " . $event['creator_office_color'] . "\n";
        echo "- ID: " . $event['creator_office_id'] . "\n";
    } else {
        echo "ISSUE: Creator has no office assigned!\n";
    }
} else {
    echo "Event 68 not found\n";
}
?>