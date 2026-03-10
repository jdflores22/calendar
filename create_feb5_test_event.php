<?php
/**
 * Create a test event for February 5th, 2026 to test conflict detection
 */

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

// Database connection
$host = $_ENV['DATABASE_HOST'] ?? 'localhost';
$dbname = $_ENV['DATABASE_NAME'] ?? 'tesda_calendar';
$username = $_ENV['DATABASE_USER'] ?? 'root';
$password = $_ENV['DATABASE_PASSWORD'] ?? '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "🔗 Connected to database successfully\n";
    
    // Check if we have any users to assign the event to
    $userStmt = $pdo->query("SELECT id, email FROM users LIMIT 1");
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "❌ No users found in database. Please create a user first.\n";
        exit(1);
    }
    
    echo "👤 Using user: {$user['email']} (ID: {$user['id']})\n";
    
    // Create test event for February 5th, 2026
    $eventData = [
        'title' => 'Test Event - Feb 5th Meeting',
        'description' => 'Test event created for conflict detection testing',
        'start_time' => '2026-02-05 10:00:00',
        'end_time' => '2026-02-05 11:00:00',
        'location' => 'Conference Room A',
        'color' => '#3B82F6',
        'priority' => 'normal',
        'status' => 'confirmed',
        'is_all_day' => false,
        'is_recurring' => false,
        'creator_id' => $user['id'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Check if event already exists
    $checkStmt = $pdo->prepare("SELECT id FROM events WHERE title = ? AND start_time = ?");
    $checkStmt->execute([$eventData['title'], $eventData['start_time']]);
    
    if ($checkStmt->fetch()) {
        echo "⚠️  Event already exists, skipping creation\n";
    } else {
        // Insert the event
        $insertStmt = $pdo->prepare("
            INSERT INTO events (title, description, start_time, end_time, location, color, priority, status, is_all_day, is_recurring, creator_id, created_at, updated_at)
            VALUES (:title, :description, :start_time, :end_time, :location, :color, :priority, :status, :is_all_day, :is_recurring, :creator_id, :created_at, :updated_at)
        ");
        
        $insertStmt->execute($eventData);
        $eventId = $pdo->lastInsertId();
        
        echo "✅ Created test event successfully!\n";
        echo "   ID: $eventId\n";
        echo "   Title: {$eventData['title']}\n";
        echo "   Time: {$eventData['start_time']} to {$eventData['end_time']}\n";
        echo "   Location: {$eventData['location']}\n";
    }
    
    // Create another overlapping event for testing
    $eventData2 = [
        'title' => 'Overlapping Workshop - Feb 5th',
        'description' => 'Another test event that overlaps with the first one',
        'start_time' => '2026-02-05 10:30:00',
        'end_time' => '2026-02-05 12:00:00',
        'location' => 'Conference Room B',
        'color' => '#EF4444',
        'priority' => 'high',
        'status' => 'confirmed',
        'is_all_day' => false,
        'is_recurring' => false,
        'creator_id' => $user['id'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    // Check if second event already exists
    $checkStmt2 = $pdo->prepare("SELECT id FROM events WHERE title = ? AND start_time = ?");
    $checkStmt2->execute([$eventData2['title'], $eventData2['start_time']]);
    
    if ($checkStmt2->fetch()) {
        echo "⚠️  Second event already exists, skipping creation\n";
    } else {
        // Insert the second event
        $insertStmt2 = $pdo->prepare("
            INSERT INTO events (title, description, start_time, end_time, location, color, priority, status, is_all_day, is_recurring, creator_id, created_at, updated_at)
            VALUES (:title, :description, :start_time, :end_time, :location, :color, :priority, :status, :is_all_day, :is_recurring, :creator_id, :created_at, :updated_at)
        ");
        
        $insertStmt2->execute($eventData2);
        $eventId2 = $pdo->lastInsertId();
        
        echo "✅ Created second test event successfully!\n";
        echo "   ID: $eventId2\n";
        echo "   Title: {$eventData2['title']}\n";
        echo "   Time: {$eventData2['start_time']} to {$eventData2['end_time']}\n";
        echo "   Location: {$eventData2['location']}\n";
    }
    
    // Show all events for February 5th
    echo "\n📅 All events for February 5th, 2026:\n";
    echo "=====================================\n";
    
    $listStmt = $pdo->prepare("
        SELECT id, title, start_time, end_time, location, priority, status 
        FROM events 
        WHERE DATE(start_time) = '2026-02-05' 
        ORDER BY start_time
    ");
    $listStmt->execute();
    
    $events = $listStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($events)) {
        echo "❌ No events found for February 5th, 2026\n";
    } else {
        foreach ($events as $event) {
            echo "• {$event['title']}\n";
            echo "  Time: {$event['start_time']} to {$event['end_time']}\n";
            echo "  Location: {$event['location']}\n";
            echo "  Priority: {$event['priority']}, Status: {$event['status']}\n";
            echo "\n";
        }
    }
    
    echo "🧪 TEST SCENARIOS:\n";
    echo "==================\n";
    echo "1. Try creating an event from 10:15 AM to 10:45 AM (should conflict with first event)\n";
    echo "2. Try creating an event from 10:45 AM to 11:30 AM (should conflict with both events)\n";
    echo "3. Try creating an event from 9:00 AM to 9:30 AM (should be clear)\n";
    echo "4. Try creating an event from 12:30 PM to 1:30 PM (should be clear)\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>