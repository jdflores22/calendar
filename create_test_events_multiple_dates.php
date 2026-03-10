<?php
/**
 * Create test events for multiple dates to demonstrate automatic conflict detection
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
    
    echo "👤 Using user: {$user['email']} (ID: {$user['id']})\n\n";
    
    // Test events for different dates
    $testEvents = [
        // February 10, 2026 - Multiple events
        [
            'title' => 'Morning Team Meeting',
            'description' => 'Weekly team sync meeting',
            'start_time' => '2026-02-10 09:00:00',
            'end_time' => '2026-02-10 10:30:00',
            'location' => 'Conference Room A',
            'color' => '#3B82F6',
            'priority' => 'high',
            'status' => 'confirmed'
        ],
        [
            'title' => 'Project Planning Session',
            'description' => 'Q1 project planning and resource allocation',
            'start_time' => '2026-02-10 10:00:00',
            'end_time' => '2026-02-10 12:00:00',
            'location' => 'Main Conference Hall',
            'color' => '#EF4444',
            'priority' => 'high',
            'status' => 'confirmed'
        ],
        [
            'title' => 'Lunch & Learn Session',
            'description' => 'Technology trends presentation',
            'start_time' => '2026-02-10 12:30:00',
            'end_time' => '2026-02-10 13:30:00',
            'location' => 'Training Room B',
            'color' => '#10B981',
            'priority' => 'normal',
            'status' => 'confirmed'
        ],
        
        // February 15, 2026 - Overlapping events
        [
            'title' => 'Budget Review Meeting',
            'description' => 'Monthly budget review and approval',
            'start_time' => '2026-02-15 14:00:00',
            'end_time' => '2026-02-15 16:00:00',
            'location' => 'Executive Conference Room',
            'color' => '#8B5CF6',
            'priority' => 'high',
            'status' => 'confirmed'
        ],
        [
            'title' => 'Staff Training Workshop',
            'description' => 'Professional development workshop',
            'start_time' => '2026-02-15 15:00:00',
            'end_time' => '2026-02-15 17:00:00',
            'location' => 'Training Center',
            'color' => '#F59E0B',
            'priority' => 'medium',
            'status' => 'confirmed'
        ],
        
        // February 20, 2026 - Single event
        [
            'title' => 'Board Meeting',
            'description' => 'Monthly board meeting',
            'start_time' => '2026-02-20 10:00:00',
            'end_time' => '2026-02-20 12:00:00',
            'location' => 'Boardroom',
            'color' => '#DC2626',
            'priority' => 'high',
            'status' => 'confirmed'
        ],
        
        // March 1, 2026 - Multiple overlapping events
        [
            'title' => 'Monthly All-Hands Meeting',
            'description' => 'Company-wide monthly meeting',
            'start_time' => '2026-03-01 09:00:00',
            'end_time' => '2026-03-01 11:00:00',
            'location' => 'Main Auditorium',
            'color' => '#059669',
            'priority' => 'high',
            'status' => 'confirmed'
        ],
        [
            'title' => 'Department Heads Meeting',
            'description' => 'Department coordination meeting',
            'start_time' => '2026-03-01 10:30:00',
            'end_time' => '2026-03-01 12:30:00',
            'location' => 'Executive Conference Room',
            'color' => '#7C3AED',
            'priority' => 'high',
            'status' => 'confirmed'
        ],
        [
            'title' => 'New Employee Orientation',
            'description' => 'Orientation for new hires',
            'start_time' => '2026-03-01 13:00:00',
            'end_time' => '2026-03-01 16:00:00',
            'location' => 'HR Training Room',
            'color' => '#0891B2',
            'priority' => 'normal',
            'status' => 'confirmed'
        ],
        [
            'title' => 'IT Security Briefing',
            'description' => 'Monthly security update and training',
            'start_time' => '2026-03-01 15:30:00',
            'end_time' => '2026-03-01 16:30:00',
            'location' => 'IT Conference Room',
            'color' => '#DC2626',
            'priority' => 'medium',
            'status' => 'confirmed'
        ]
    ];
    
    $createdCount = 0;
    $skippedCount = 0;
    
    foreach ($testEvents as $eventData) {
        // Add common fields
        $eventData['is_all_day'] = false;
        $eventData['is_recurring'] = false;
        $eventData['creator_id'] = $user['id'];
        $eventData['created_at'] = date('Y-m-d H:i:s');
        $eventData['updated_at'] = date('Y-m-d H:i:s');
        
        // Check if event already exists
        $checkStmt = $pdo->prepare("SELECT id FROM events WHERE title = ? AND start_time = ?");
        $checkStmt->execute([$eventData['title'], $eventData['start_time']]);
        
        if ($checkStmt->fetch()) {
            echo "⚠️  Event '{$eventData['title']}' already exists, skipping\n";
            $skippedCount++;
        } else {
            // Insert the event
            $insertStmt = $pdo->prepare("
                INSERT INTO events (title, description, start_time, end_time, location, color, priority, status, is_all_day, is_recurring, creator_id, created_at, updated_at)
                VALUES (:title, :description, :start_time, :end_time, :location, :color, :priority, :status, :is_all_day, :is_recurring, :creator_id, :created_at, :updated_at)
            ");
            
            $insertStmt->execute($eventData);
            $eventId = $pdo->lastInsertId();
            
            echo "✅ Created: {$eventData['title']} (ID: $eventId)\n";
            echo "   📅 {$eventData['start_time']} to {$eventData['end_time']}\n";
            echo "   📍 {$eventData['location']}\n\n";
            $createdCount++;
        }
    }
    
    echo "📊 SUMMARY:\n";
    echo "===========\n";
    echo "✅ Created: $createdCount events\n";
    echo "⚠️  Skipped: $skippedCount events (already exist)\n\n";
    
    // Show events by date for testing
    echo "🗓️  EVENTS BY DATE (for testing conflict detection):\n";
    echo "=====================================================\n";
    
    $dates = [
        '2026-02-10' => 'February 10, 2026',
        '2026-02-15' => 'February 15, 2026', 
        '2026-02-20' => 'February 20, 2026',
        '2026-03-01' => 'March 1, 2026'
    ];
    
    foreach ($dates as $date => $displayDate) {
        echo "\n📅 $displayDate:\n";
        echo str_repeat('-', strlen($displayDate) + 4) . "\n";
        
        $listStmt = $pdo->prepare("
            SELECT title, start_time, end_time, location, priority 
            FROM events 
            WHERE DATE(start_time) = ? 
            ORDER BY start_time
        ");
        $listStmt->execute([$date]);
        
        $events = $listStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($events)) {
            echo "   (No events)\n";
        } else {
            foreach ($events as $event) {
                $startTime = date('g:i A', strtotime($event['start_time']));
                $endTime = date('g:i A', strtotime($event['end_time']));
                echo "   • {$event['title']}\n";
                echo "     ⏰ $startTime - $endTime\n";
                echo "     📍 {$event['location']}\n";
                echo "     🔥 Priority: {$event['priority']}\n\n";
            }
        }
    }
    
    echo "🧪 TESTING INSTRUCTIONS:\n";
    echo "========================\n";
    echo "1. Go to the calendar page: http://127.0.0.4:8000/calendar\n";
    echo "2. Click on any of these dates to test automatic conflict detection:\n";
    echo "   • February 10, 2026 (3 events, some overlapping)\n";
    echo "   • February 15, 2026 (2 overlapping events)\n";
    echo "   • February 20, 2026 (1 event)\n";
    echo "   • March 1, 2026 (4 events, multiple overlaps)\n";
    echo "3. The modal should automatically show ALL conflicts for each date!\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>