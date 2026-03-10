<?php
// Simple test to check database time storage

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env.local'); // Load local environment first

// Parse DATABASE_URL from .env.local
$databaseUrl = $_ENV['DATABASE_URL'] ?? '';
if ($databaseUrl) {
    $parsed = parse_url($databaseUrl);
    $host = $parsed['host'] ?? 'localhost';
    $dbname = ltrim($parsed['path'] ?? '', '/');
    $username = $parsed['user'] ?? 'root';
    $password = $parsed['pass'] ?? '';
    $port = $parsed['port'] ?? 3306;
} else {
    // Fallback to individual variables
    $host = $_ENV['DATABASE_HOST'] ?? 'localhost';
    $dbname = $_ENV['DATABASE_NAME'] ?? 'tesda_calendar';
    $username = $_ENV['DATABASE_USER'] ?? 'root';
    $password = $_ENV['DATABASE_PASSWORD'] ?? '';
    $port = $_ENV['DATABASE_PORT'] ?? 3306;
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== Database Time Analysis ===\n\n";
    echo "Connected to database: $dbname on $host:$port\n\n";
    
    // Get current database time and timezone
    $stmt = $pdo->query("SELECT NOW() as now_time");
    $times = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get timezone info separately  
    $stmt2 = $pdo->query("SELECT @@session.time_zone as tz");
    $timezone = $stmt2->fetch(PDO::FETCH_ASSOC);
    
    // Get UTC timestamp
    $stmt3 = $pdo->query("SELECT UTC_TIMESTAMP() as utc_now");
    $utc = $stmt3->fetch(PDO::FETCH_ASSOC);
    
    echo "Database Current Time: " . $times['now_time'] . "\n";
    echo "Database Timezone: " . $timezone['tz'] . "\n";
    echo "Database UTC Time: " . $utc['utc_now'] . "\n\n";
    
    // Get some sample events
    $stmt = $pdo->query("
        SELECT 
            id, 
            title, 
            start_time, 
            end_time, 
            is_all_day,
            created_at,
            updated_at
        FROM events 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== Recent Events ===\n\n";
    
    if (empty($events)) {
        echo "No events found in database.\n\n";
    } else {
        foreach ($events as $event) {
            echo "Event: " . $event['title'] . "\n";
            echo "  ID: " . $event['id'] . "\n";
            echo "  Start Time (DB): " . $event['start_time'] . "\n";
            echo "  End Time (DB): " . $event['end_time'] . "\n";
            echo "  All Day: " . ($event['is_all_day'] ? 'Yes' : 'No') . "\n";
            echo "  Created: " . $event['created_at'] . "\n";
            echo "  Updated: " . $event['updated_at'] . "\n";
            
            // Convert to Philippines timezone for display
            $startTime = new DateTime($event['start_time'], new DateTimeZone('UTC'));
            $startTime->setTimezone(new DateTimeZone('Asia/Manila'));
            
            $endTime = new DateTime($event['end_time'], new DateTimeZone('UTC'));
            $endTime->setTimezone(new DateTimeZone('Asia/Manila'));
            
            echo "  Start Time (PH): " . $startTime->format('Y-m-d H:i:s T') . "\n";
            echo "  End Time (PH): " . $endTime->format('Y-m-d H:i:s T') . "\n";
            echo "\n";
        }
    }
    
    // Check tagged offices
    echo "=== Tagged Offices Check ===\n\n";
    
    $stmt = $pdo->query("
        SELECT 
            e.id,
            e.title,
            o.name as office_name,
            o.color as office_color
        FROM events e
        JOIN event_offices eo ON e.id = eo.event_id
        JOIN offices o ON eo.office_id = o.id
        ORDER BY e.id DESC
        LIMIT 10
    ");
    
    $taggedOffices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($taggedOffices)) {
        echo "No events with tagged offices found.\n";
    } else {
        foreach ($taggedOffices as $tagged) {
            echo "Event: " . $tagged['title'] . " (ID: " . $tagged['id'] . ")\n";
            echo "  Tagged Office: " . $tagged['office_name'] . " (Color: " . $tagged['office_color'] . ")\n\n";
        }
    }
    
    // Test timezone conversion
    echo "=== Timezone Conversion Test ===\n\n";
    
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    echo "Current Philippines Time: " . $now->format('Y-m-d H:i:s T') . "\n";
    
    $utcNow = clone $now;
    $utcNow->setTimezone(new DateTimeZone('UTC'));
    echo "Same time in UTC: " . $utcNow->format('Y-m-d H:i:s T') . "\n";
    
    $backToPH = clone $utcNow;
    $backToPH->setTimezone(new DateTimeZone('Asia/Manila'));
    echo "Converted back to PH: " . $backToPH->format('Y-m-d H:i:s T') . "\n\n";
    
} catch (PDOException $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n";
    echo "DSN used: mysql:host=$host;port=$port;dbname=$dbname\n";
    echo "Username: $username\n";
}

echo "Test completed.\n";
?>