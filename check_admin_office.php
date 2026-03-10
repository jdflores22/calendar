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

// Get admin user's office information
$stmt = $pdo->query("
    SELECT 
        u.id,
        u.email,
        u.office_id,
        o.name as office_name,
        o.color as office_color
    FROM users u
    LEFT JOIN offices o ON u.office_id = o.id
    WHERE u.email = 'admin@tesda.gov.ph'
");

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "=== Admin User Office Information ===\n";
    echo "User ID: " . $user['id'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "Office ID: " . ($user['office_id'] ?? 'NULL') . "\n";
    echo "Office Name: " . ($user['office_name'] ?? 'No office assigned') . "\n";
    echo "Office Color: " . ($user['office_color'] ?? 'No color') . "\n";
} else {
    echo "Admin user not found\n";
}
?>