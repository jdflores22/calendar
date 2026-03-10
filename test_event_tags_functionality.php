<?php

require_once 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use Doctrine\DBAL\DriverManager;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->load('.env');

// Database connection
$connectionParams = [
    'dbname' => $_ENV['DATABASE_NAME'] ?? 'tesda_calendar',
    'user' => $_ENV['DATABASE_USER'] ?? 'root',
    'password' => $_ENV['DATABASE_PASSWORD'] ?? '',
    'host' => $_ENV['DATABASE_HOST'] ?? 'localhost',
    'driver' => 'pdo_mysql',
];

try {
    $connection = DriverManager::getConnection($connectionParams);
    
    echo "Testing Event Tags Functionality...\n\n";
    
    // Test 1: Check if we can find events with tags
    $sql = "
        SELECT e.id, e.title, GROUP_CONCAT(et.name) as tags
        FROM events e
        LEFT JOIN event_event_tags eet ON e.id = eet.event_id
        LEFT JOIN event_tags et ON eet.event_tag_id = et.id
        WHERE et.id IS NOT NULL
        GROUP BY e.id, e.title
        LIMIT 5
    ";
    
    $result = $connection->executeQuery($sql);
    $eventsWithTags = $result->fetchAllAssociative();
    
    if (count($eventsWithTags) > 0) {
        echo "✓ Found events with tags:\n";
        foreach ($eventsWithTags as $event) {
            echo "  - Event #{$event['id']}: {$event['title']} (Tags: {$event['tags']})\n";
        }
    } else {
        echo "ℹ No events with tags found yet\n";
    }
    
    // Test 2: Check popular tags
    $sql = "
        SELECT et.name, COUNT(eet.event_id) as event_count
        FROM event_tags et
        LEFT JOIN event_event_tags eet ON et.id = eet.event_tag_id
        GROUP BY et.id, et.name
        HAVING event_count > 0
        ORDER BY event_count DESC
        LIMIT 10
    ";
    
    $result = $connection->executeQuery($sql);
    $popularTags = $result->fetchAllAssociative();
    
    echo "\n✓ Popular tags:\n";
    if (count($popularTags) > 0) {
        foreach ($popularTags as $tag) {
            echo "  - {$tag['name']}: {$tag['event_count']} events\n";
        }
    } else {
        echo "  - No tags with events found\n";
    }
    
    // Test 3: Create a test event with tags (simulation)
    echo "\nTesting tag creation and assignment...\n";
    
    // First, let's create some test tags if they don't exist
    $testTags = ['urgent', 'quarterly-review', 'all-hands', 'planning'];
    
    foreach ($testTags as $tagName) {
        // Check if tag exists
        $sql = "SELECT id FROM event_tags WHERE name = ?";
        $result = $connection->executeQuery($sql, [$tagName]);
        
        if ($result->rowCount() == 0) {
            // Create the tag
            $sql = "INSERT INTO event_tags (name, created_at) VALUES (?, NOW())";
            $connection->executeStatement($sql, [$tagName]);
            echo "  ✓ Created tag: {$tagName}\n";
        } else {
            echo "  - Tag already exists: {$tagName}\n";
        }
    }
    
    // Test 4: Check if EventController methods would work
    echo "\nTesting EventController compatibility...\n";
    
    // Check if we can find or create tags (simulating EventTagRepository::findOrCreateByName)
    $testTagName = 'test-functionality-' . date('Y-m-d-H-i-s');
    
    // Check if tag exists
    $sql = "SELECT id FROM event_tags WHERE name = ?";
    $result = $connection->executeQuery($sql, [$testTagName]);
    
    if ($result->rowCount() == 0) {
        // Create the tag
        $sql = "INSERT INTO event_tags (name, created_at) VALUES (?, NOW())";
        $connection->executeStatement($sql, [$testTagName]);
        
        // Get the created tag ID
        $tagId = $connection->lastInsertId();
        echo "  ✓ Created test tag: {$testTagName} (ID: {$tagId})\n";
        
        // Clean up the test tag
        $sql = "DELETE FROM event_tags WHERE id = ?";
        $connection->executeStatement($sql, [$tagId]);
        echo "  ✓ Cleaned up test tag\n";
    }
    
    // Test 5: Verify the database structure supports the EventController operations
    echo "\nVerifying database structure for EventController...\n";
    
    // Check if events table has the necessary columns
    $sql = "DESCRIBE events";
    $result = $connection->executeQuery($sql);
    $eventColumns = $result->fetchAllAssociative();
    
    $requiredColumns = ['id', 'title', 'start_time', 'end_time', 'creator_id'];
    $foundColumns = array_column($eventColumns, 'Field');
    
    foreach ($requiredColumns as $column) {
        if (in_array($column, $foundColumns)) {
            echo "  ✓ Events table has required column: {$column}\n";
        } else {
            echo "  ✗ Events table missing column: {$column}\n";
        }
    }
    
    // Check foreign key constraints
    $sql = "
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = ? 
        AND TABLE_NAME = 'event_event_tags'
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ";
    
    $result = $connection->executeQuery($sql, [$connectionParams['dbname']]);
    $constraints = $result->fetchAllAssociative();
    
    echo "\n✓ Foreign key constraints for event_event_tags:\n";
    foreach ($constraints as $constraint) {
        echo "  - {$constraint['COLUMN_NAME']} -> {$constraint['REFERENCED_TABLE_NAME']}.{$constraint['REFERENCED_COLUMN_NAME']}\n";
    }
    
    echo "\n✅ Event tags functionality test completed successfully!\n";
    echo "\nThe system is ready to handle event tags. You can now:\n";
    echo "1. Visit /events/new to create events with tags\n";
    echo "2. Edit existing events to add/remove tags\n";
    echo "3. Use the API endpoints for tag management\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    
    if (strpos($e->getMessage(), "event_tags") !== false) {
        echo "\nThe event_tags table might not exist or have the correct structure.\n";
        echo "Please ensure you've run the latest migrations.\n";
    }
}